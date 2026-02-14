<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\Screening;
use App\Models\ScreeningSeat;
use App\Models\Seat;
use App\Services\SeatInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeatInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SeatInventoryService $service;
    protected Screening $screening;
    protected array $seats = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SeatInventoryService::class);

        // Setup: crear screening con asientos
        $this->screening = Screening::factory()->create();
        $room = $this->screening->room;

        // Crear algunos asientos
        for ($i = 1; $i <= 5; $i++) {
            $this->seats[$i] = Seat::factory()
                ->forRoom($room)
                ->create(['row_number' => 1, 'seat_number' => $i]);
        }
    }

    /**
     * Test: ensureScreeningSeats es idempotente
     */
    public function test_ensure_screening_seats_is_idempotent()
    {
        // Primera ejecución
        $result1 = $this->service->ensureScreeningSeats($this->screening->id);
        $this->assertEquals(5, $result1['created']);
        $this->assertEquals(0, $result1['existing']);

        // Segunda ejecución (debería ser sin cambios)
        $result2 = $this->service->ensureScreeningSeats($this->screening->id);
        $this->assertEquals(0, $result2['created']);
        $this->assertEquals(5, $result2['existing']);

        // Verificar que solo hay 5 registros en total
        $count = ScreeningSeat::where('screening_id', $this->screening->id)->count();
        $this->assertEquals(5, $count);
    }

    /**
     * Test: Reservar asientos exitosamente
     */
    public function test_reserve_seats_success()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        $seat_ids = [
            $this->seats[1]->id,
            $this->seats[2]->id,
            $this->seats[3]->id,
        ];

        $result = $this->service->reserveSeats(
            $this->screening->id,
            $seat_ids,
            'test_user',
            'user_123',
            3600 // 1 hora
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(3, count($result['reserved']));
        $this->assertEmpty($result['failed']);

        // Verificar en BD
        $reserved = ScreeningSeat::where('screening_id', $this->screening->id)
            ->where('status', ScreeningSeat::STATUS_RESERVED)
            ->count();
        $this->assertEquals(3, $reserved);
    }

    /**
     * Test: No se puede reservar un asiento ya vendido
     */
    public function test_cannot_reserve_sold_seat()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        // Marcar un asiento como vendido manualmente
        ScreeningSeat::where('screening_id', $this->screening->id)
            ->where('seat_id', $this->seats[1]->id)
            ->update(['status' => ScreeningSeat::STATUS_SOLD]);

        $seat_ids = [
            $this->seats[1]->id,
            $this->seats[2]->id,
        ];

        $result = $this->service->reserveSeats(
            $this->screening->id,
            $seat_ids,
            'test_user',
            'user_123',
            3600
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(1, count($result['reserved']));
        $this->assertArrayHasKey($this->seats[1]->id, $result['failed']);
        $this->assertStringContainsString('Sold', $result['failed'][$this->seats[1]->id]);
    }

    /**
     * Test: No se puede reservar un asiento ya reservado (no expirado)
     */
    public function test_cannot_reserve_already_reserved_seat()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        // Reservar asiento por primera vez
        $this->service->reserveSeats(
            $this->screening->id,
            [$this->seats[1]->id],
            'user_type',
            'user_1',
            3600
        );

        // Intentar reservar el mismo asiento
        $result = $this->service->reserveSeats(
            $this->screening->id,
            [$this->seats[1]->id],
            'user_type',
            'user_2',
            3600
        );

        $this->assertFalse($result['success']);
        $this->assertEmpty($result['reserved']);
        $this->assertArrayHasKey($this->seats[1]->id, $result['failed']);
    }

    /**
     * Test: Se puede reclamar un asiento con reserva expirada
     */
    public function test_can_reclaim_expired_reservation()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        // Reservar asiento con TTL muy corto
        $this->service->reserveSeats(
            $this->screening->id,
            [$this->seats[1]->id],
            'user_type',
            'user_1',
            1 // 1 segundo
        );

        // Esperar a que expire
        sleep(2);

        // Intentar reservar el mismo asiento debería funcionar
        $result = $this->service->reserveSeats(
            $this->screening->id,
            [$this->seats[1]->id],
            'user_type',
            'user_2',
            3600
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1, count($result['reserved']));
    }

    /**
     * Test: Liberar asientos de una orden
     */
    public function test_release_seats_by_order()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        $order = Order::factory()->create();

        $seat_ids = [$this->seats[1]->id, $this->seats[2]->id];

        // Reservar con order_id
        $this->service->reserveSeats(
            $this->screening->id,
            $seat_ids,
            'user_type',
            'user_123',
            3600,
            $order->id
        );

        // Liberar
        $released = $this->service->releaseSeatsByOrder($order->id, 'test_cancellation');

        $this->assertEquals(2, $released);

        // Verificar que están disponibles
        $available = ScreeningSeat::forScreening($this->screening->id)
            ->available()
            ->count();
        $this->assertEquals(5, $available); // Todos vuelven a estar disponibles
    }

    /**
     * Test: Marcar asientos como vendidos
     */
    public function test_mark_sold_by_order()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        $order = Order::factory()->create();

        $seat_ids = [$this->seats[1]->id, $this->seats[2]->id];

        // Reservar
        $this->service->reserveSeats(
            $this->screening->id,
            $seat_ids,
            'user_type',
            'user_123',
            3600,
            $order->id
        );

        // Marcar como vendidos
        $sold = $this->service->markSoldByOrder($order->id);

        $this->assertEquals(2, $sold);

        // Verificar en BD
        $sold_count = ScreeningSeat::where('order_id', $order->id)
            ->where('status', ScreeningSeat::STATUS_SOLD)
            ->count();
        $this->assertEquals(2, $sold_count);
    }

    /**
     * Test: Obtener inventario (estadísticas)
     */
    public function test_get_screening_inventory()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        $order = Order::factory()->create();

        // Reservar 2 asientos
        $this->service->reserveSeats(
            $this->screening->id,
            [$this->seats[1]->id, $this->seats[2]->id],
            'user_type',
            'user_123',
            3600,
            $order->id
        );

        // Marcar 1 como vendido
        ScreeningSeat::where('screening_id', $this->screening->id)
            ->where('seat_id', $this->seats[1]->id)
            ->update(['status' => ScreeningSeat::STATUS_SOLD]);

        $inventory = $this->service->getScreeningInventory($this->screening->id);

        $this->assertEquals(3, $inventory['available']); // 5 total - 2 reserved + 1 en reserved que ahora es sold = 3 avail
        // Recalcular manualmente para test
        $this->assertEquals(5, $inventory['total']);
    }

    /**
     * Test: Reclamar reservas expiradas
     */
    public function test_reclaim_expired_reservations()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        // Reservar con TTL corto
        $this->service->reserveSeats(
            $this->screening->id,
            [$this->seats[1]->id, $this->seats[2]->id],
            'user_type',
            'user_123',
            1 // 1 segundo
        );

        // Esperar
        sleep(2);

        // Reclamar
        $reclaimed = $this->service->reclaimExpiredReservations($this->screening->id);

        $this->assertEquals(2, $reclaimed);

        // Verificar que están disponibles
        $available = ScreeningSeat::forScreening($this->screening->id)
            ->available()
            ->count();
        $this->assertEquals(5, $available);
    }

    /**
     * Test: Concurrencia - simulación de race condition
     * Múltiples intentos de reservar el mismo asiento simultáneamente
     */
    public function test_concurrent_reservation_isolation()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        $seat_id = $this->seats[1]->id;
        $screening_id = $this->screening->id;

        // Intentar reservar el mismo asiento 3 veces (simulado secuencial pero con lock)
        $results = [];
        for ($i = 1; $i <= 3; $i++) {
            $result = $this->service->reserveSeats(
                $screening_id,
                [$seat_id],
                'user_type',
                "user_{$i}",
                3600
            );
            $results[$i] = $result;
        }

        // Solo 1 debería tener éxito
        $successful = array_filter($results, fn($r) => $r['success']);
        $this->assertCount(1, $successful);

        // Los otros 2 deberían haber fallado
        $failed = array_filter($results, fn($r) => !$r['success']);
        $this->assertCount(2, $failed);

        // Verificar que en BD solo hay 1 reserva
        $reserved = ScreeningSeat::where('screening_id', $screening_id)
            ->where('seat_id', $seat_id)
            ->where('status', ScreeningSeat::STATUS_RESERVED)
            ->count();
        $this->assertEquals(1, $reserved);
    }

    /**
     * Test: Obtener asientos disponibles
     */
    public function test_get_available_seats()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        // Reservar 1 asiento
        $this->service->reserveSeats(
            $this->screening->id,
            [$this->seats[1]->id],
            'user_type',
            'user_123',
            3600
        );

        $available = $this->service->getAvailableSeats($this->screening->id);

        // Deberían haber 4 disponibles (5 total - 1 reservado)
        $this->assertCount(4, $available);
        $this->assertArrayNotHasKey($this->seats[1]->id, $available);
        $this->assertArrayHasKey($this->seats[2]->id, $available);
    }

    /**
     * Test: No se puede reservar asientos inexistentes
     */
    public function test_cannot_reserve_non_existent_seats()
    {
        $this->service->ensureScreeningSeats($this->screening->id);

        $result = $this->service->reserveSeats(
            $this->screening->id,
            [999999], // seat_id que no existe
            'user_type',
            'user_123',
            3600
        );

        $this->assertFalse($result['success']);
        $this->assertEmpty($result['reserved']);
        $this->assertArrayHasKey(999999, $result['failed']);
    }
}
