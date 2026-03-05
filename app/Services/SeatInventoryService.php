<?php

namespace App\Services;

use App\Models\ScreeningSeat;
use App\Models\Screening;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de inventario de asientos de funciones
 * Maneja la reserva, liberación y venta de asientos con protección contra race conditions
 */
class SeatInventoryService
{
    const MARKER_SEAT_UNAVAILABLE = 'UNAVAILABLE';

    /**
     * Asegura que todos los asientos del room de un screening tengan entrada en screening_seats
     * Idempotente: puede ejecutarse múltiples veces sin efectos secundarios
     *
     * @param int $screening_id
     * @return array ['created' => int, 'existing' => int]
     */
    public function ensureScreeningSeats(int $screening_id): array
    {
        $screening = Screening::findOrFail($screening_id);
        $room = $screening->room;

        $seats = $room->seats()->where('is_active', true)->get();

        if ($seats->isEmpty()) {
            return ['created' => 0, 'existing' => 0];
        }

        $created = 0;
        $existing = 0;

        foreach ($seats as $seat) {
            // Crear solo si no existe para no pisar estados sold/reserved.
            $screening_seat = ScreeningSeat::firstOrCreate(
                [
                    'screening_id' => $screening_id,
                    'seat_id' => $seat->id,
                ],
                [
                    'status' => ScreeningSeat::STATUS_AVAILABLE,
                ]
            );

            if ($screening_seat->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        Log::info("Screening seats ensured", [
            'screening_id' => $screening_id,
            'created' => $created,
            'existing' => $existing,
        ]);

        return ['created' => $created, 'existing' => $existing];
    }

    /**
     * Intenta reservar un conjunto de asientos de forma atómica
     * Usa transacciones y SELECT FOR UPDATE para evitar race conditions
     *
     * @param int $screening_id
     * @param array $seat_ids Lista de IDs de asientos a reservar
     * @param string $holder_type Tipo de holder (terminal, session, user, etc)
     * @param string $holder_id ID del holder
     * @param int $ttl_seconds Segundos que durará la reserva
     * @param int|null $order_id ID de orden (opcional)
     *
     * @return array [
     *     'success' => bool,
     *     'reserved' => array (seat_ids),
     *     'failed' => array [seat_id => 'reason']
     * ]
     */
    public function reserveSeats(
        int $screening_id,
        array $seat_ids,
        string $holder_type,
        string $holder_id,
        int $ttl_seconds,
        ?int $order_id = null
    ): array {
        $reserved = [];
        $failed = [];

        if (empty($seat_ids)) {
            return ['success' => false, 'reserved' => [], 'failed' => ['global' => 'No seats provided']];
        }

        DB::transaction(function () use (
            $screening_id,
            $seat_ids,
            $holder_type,
            $holder_id,
            $ttl_seconds,
            $order_id,
            &$reserved,
            &$failed
        ) {
            // Obtener rows con lock pessimista
            $screening_seats = ScreeningSeat::query()
                ->where('screening_id', $screening_id)
                ->whereIn('seat_id', $seat_ids)
                ->lockForUpdate()
                ->get();

            if ($screening_seats->count() !== count($seat_ids)) {
                $found_ids = $screening_seats->pluck('seat_id')->toArray();
                $missing = array_diff($seat_ids, $found_ids);
                foreach ($missing as $seat_id) {
                    $failed[$seat_id] = 'Does not exist for this screening';
                }
            }

            $reserved_until = now()->addSeconds($ttl_seconds);

            foreach ($screening_seats as $ss) {
                $seat_id = $ss->seat_id;

                // Validar estado
                if ($ss->status === ScreeningSeat::STATUS_SOLD) {
                    $failed[$seat_id] = 'Sold';
                    continue;
                }

                if ($ss->status === ScreeningSeat::STATUS_RESERVED) {
                    // Si reserva expiró, permitir reclamar
                    if (!$ss->isReservationExpired()) {
                        $failed[$seat_id] = 'Reserved by someone else';
                        continue;
                    }
                    // Reclamar: Ya lo vamos a actualizar
                }

                // Actualizar a reserved
                $ss->update([
                    'status' => ScreeningSeat::STATUS_RESERVED,
                    'reserved_until' => $reserved_until,
                    'reserved_by_type' => $holder_type,
                    'reserved_by_id' => $holder_id,
                    'order_id' => $order_id,
                ]);

                $reserved[] = $seat_id;
            }
        }, attempts: 5);

        $success = empty($failed);

        Log::info('Seats reservation attempt', [
            'screening_id' => $screening_id,
            'requested' => count($seat_ids),
            'reserved' => count($reserved),
            'failed' => count($failed),
            'holder_type' => $holder_type,
            'holder_id' => $holder_id,
        ]);

        return [
            'success' => $success,
            'reserved' => $reserved,
            'failed' => $failed,
        ];
    }

    /**
     * Libera todos los asientos reservados para una orden (ej: si el usuario cancela)
     *
     * @param int $order_id
     * @param string $reason Razón de liberación (cancelation, expiration, etc)
     * @return int Cantidad de asientos liberados
     */
    public function releaseSeatsByOrder(int $order_id, string $reason = 'manual_release'): int
    {
        $released = 0;

        DB::transaction(function () use ($order_id, &$released) {
            $screening_seats = ScreeningSeat::where('order_id', $order_id)
                ->where('status', ScreeningSeat::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            foreach ($screening_seats as $ss) {
                $ss->update([
                    'status' => ScreeningSeat::STATUS_AVAILABLE,
                    'reserved_until' => null,
                    'reserved_by_type' => null,
                    'reserved_by_id' => null,
                    'order_id' => null,
                ]);
                $released++;
            }
        });

        Log::info('Seats released by order', [
            'order_id' => $order_id,
            'released' => $released,
            'reason' => $reason,
        ]);

        return $released;
    }

    /**
     * Marca todos los asientos reservados para una orden como vendidos
     * Se llama cuando el pago se confirma
     *
     * @param int $order_id
     * @return int Cantidad de asientos marcados como sold
     */
    public function markSoldByOrder(int $order_id): int
    {
        $sold = 0;

        DB::transaction(function () use ($order_id, &$sold) {
            $screening_seats = ScreeningSeat::where('order_id', $order_id)
                ->where('status', ScreeningSeat::STATUS_RESERVED)
                ->lockForUpdate()
                ->get();

            foreach ($screening_seats as $ss) {
                $ss->update([
                    'status' => ScreeningSeat::STATUS_SOLD,
                    'sold_at' => now(),
                ]);
                $sold++;
            }
        });

        Log::info('Seats marked as sold', [
            'order_id' => $order_id,
            'sold_count' => $sold,
        ]);

        return $sold;
    }

    /**
     * Obtiene estadísticas de disponibilidad para un screening
     *
     * @param int $screening_id
     * @return array ['available' => int, 'reserved' => int, 'sold' => int, 'total' => int]
     */
    public function getScreeningInventory(int $screening_id): array
    {
        $stats = ScreeningSeat::forScreening($screening_id)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'available' => $stats[ScreeningSeat::STATUS_AVAILABLE] ?? 0,
            'reserved' => $stats[ScreeningSeat::STATUS_RESERVED] ?? 0,
            'sold' => $stats[ScreeningSeat::STATUS_SOLD] ?? 0,
            'total' => array_sum($stats),
        ];
    }

    /**
     * Recaima asientos con reservas expiradas y los pone en available
     * Útil para limpeza ejecutada por un cron job
     *
     * @param int|null $screening_id Si se proporciona, solo reclamar para ese screening
     * @return int Cantidad de asientos reclamados
     */
    public function reclaimExpiredReservations(?int $screening_id = null): int
    {
        $query = ScreeningSeat::expiredReservationsWithoutPayment();

        if ($screening_id) {
            $query->where('screening_id', $screening_id);
        }

        $reclaimed = $query->update([
            'status' => ScreeningSeat::STATUS_AVAILABLE,
            'reserved_until' => null,
            'reserved_by_type' => null,
            'reserved_by_id' => null,
            'order_id' => null,
        ]);

        Log::info('Expired reservations reclaimed', [
            'screening_id' => $screening_id,
            'reclaimed_count' => $reclaimed,
        ]);

        return $reclaimed;
    }

    /**
     * Obtiene lista de asientos disponibles para un screening
     * Con opción de filtro adicionales
     *
     * @param int $screening_id
     * @return array [seat_id => seat_info]
     */
    public function getAvailableSeats(int $screening_id): array
    {
        $seats = ScreeningSeat::forScreening($screening_id)
            ->where('status', ScreeningSeat::STATUS_AVAILABLE)
            ->with('seat')
            ->get();

        $available = [];
        foreach ($seats as $ss) {
            $available[$ss->seat_id] = [
                'seat_code' => $ss->seat->seat_code,
                'row_number' => $ss->seat->row_number,
                'seat_number' => $ss->seat->seat_number,
                'type' => $ss->seat->type,
                'price_modifier' => $ss->seat->price_modifier,
            ];
        }

        return $available;
    }

    /**
     * Obtiene IDs de asientos reservados para una orden
     * Se usa en FinalizeOrderPaymentAction para crear tickets
     *
     * @param int $order_id
     * @return array Lista de seat_ids
     */
    public function getReservedSeatIdsByOrder(int $order_id): array
    {
        return ScreeningSeat::where('order_id', $order_id)
            ->where('status', ScreeningSeat::STATUS_RESERVED)
            ->pluck('seat_id')
            ->toArray();
    }

    /**
     * Marca asientos de una orden como SOLD (después de payment confirmado)
     * Alias más semántico para markSoldByOrder
     * Se llama desde FinalizeOrderPaymentAction
     *
     * @param int $order_id
     * @return int Cantidad de asientos marcados como sold
     */
    public function finalizeOrderSeatsToSold(int $order_id): int
    {
        return $this->markSoldByOrder($order_id);
    }
}
