<?php

namespace App\Enums;

/**
 * Estados Unificados del Sistema de Pagos
 * 
 * Esta es la FUENTE ÚNICA DE VERDAD para todos los estados
 * relacionados con pagos y órdenes en CINEA.
 * 
 * IMPORTANTE:
 * - Todos los modelos (Order, Ticket, PaymentProviderTicket) usan SOLO estos estados
 * - No crear constantes locales en modelos, usar estas
 * - Campos ENUM en BD deben estar sincronizados
 * 
 * FLUJOS PRINCIPALES:
 * 
 * 1. COMPRA NORMAL:
 *    PENDING → PROCESSING → COMPLETED ✅
 * 
 * 2. COMPRA RECHAZADA:
 *    PENDING → PROCESSING → FAILED ❌
 * 
 * 3. CANCELACIÓN POR USUARIO:
 *    PENDING → CANCELLED 🚫
 *    o
 *    PROCESSING → CANCELLED 🚫
 * 
 * 4. EXPIRACIÓN POR TTL:
 *    PENDING → EXPIRED ⏰
 *    o
 *    PROCESSING → EXPIRED ⏰
 * 
 * 5. REEMBOLSO (post-pago):
 *    COMPLETED → REFUNDED 🔄
 */

class PaymentStatus
{
    // ============================================================================
    // ESTADOS PRINCIPALES (Usados en Order, Ticket, PaymentProviderTicket)
    // ============================================================================
    
    /**
     * PENDING
     * 
     * Estado inicial. Entidad creada pero sin confirmación.
     * 
     * En Order:
     *   - Estado inicial cuando se crea una orden (ORDER-FIRST flow)
     *   - Asientos están reserved en ScreeningSeat
     * 
     * En Ticket:
     *   - Ticket pendiente de confirmación
     *   - Será confirmado cuando webhook aprueba pago
     * 
     * En PaymentProviderTicket:
     *   - Creado pero no iniciado en el provider aún
     *   - O simplemente registrado sin procesamiento
     * 
     * TTL: 6 minutos (para Orders con reserve de asientos)
     */
    const STATUS_PENDING = 'pending';
    
    /**
     * PROCESSING
     * 
     * En transición. Esperando confirmación/resolución.
     * 
     * En Order:
     *   - Pago está siendo procesado por provider
     *   - Asientos siguen reserved
     * 
     * En Ticket:
     *   - Pago está en proceso
     * 
     * En PaymentProviderTicket:
     *   - Enviado al provider (queued, en cola, esperando)
     *   - No hay respuesta aún
     * 
     * TTL: Depende del provider (10-30 minutos)
     * Auto-transición: PROCESSING → EXPIRED si timeout
     */
    const STATUS_PROCESSING = 'processing';
    
    /**
     * COMPLETED
     * 
     * Exitosamente confirmado ✅
     * 
     * En Order:
     *   - Pago aprobado por provider
     *   - Tickets fueron creados y confirmados
     *   - Asientos marcados como SOLD en ScreeningSeat
     * 
     * En Ticket:
     *   - Confirmado y listo para usar
     *   - ticket_number y qr_code generados
     * 
     * En PaymentProviderTicket:
     *   - Aprobado por provider
     *   - completed_at registrado
     * 
     * NO expira. Final feliz.
     */
    const STATUS_COMPLETED = 'completed';
    
    /**
     * FAILED
     * 
     * Fallo terminal en el proceso ❌
     * 
     * En Order:
     *   - Pago fue rechazado por provider
     *   - Asientos de-reservados (vuelven a available)
     *   - Usuario puede reintentar
     * 
     * En Ticket:
     *   - Payment falló
     *   - Ticket nunca será usado
     * 
     * En PaymentProviderTicket:
     *   - Provider rechazó
     *   - O error en finalización de orden
     *   - Pero no es por timeout (ese es EXPIRED)
     */
    const STATUS_FAILED = 'failed';
    
    /**
     * CANCELLED
     * 
     * Cancelado por usuario o sistema 🚫
     * 
     * En Order:
     *   - Usuario cancela antes de pagar
     *   - O sistema cancela por otra razón
     *   - Asientos de-reservados
     * 
     * En Ticket:
     *   - Usuario cancela su entrada
     * 
     * En PaymentProviderTicket:
     *   - Usuario cancela pago en progreso
     *   - O sistema revoca
     * 
     * Diferencia con FAILED:
     *   - FAILED = error del provider/sistema
     *   - CANCELLED = decisión del usuario
     */
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * EXPIRED
     * 
     * Tiempo de TTL vencido ⏰
     * 
     * En Order:
     *   - Reserva de asientos expiró (6 minutos)
     *   - Asientos vuelven a available automáticamente
     *   - Usuario debe reintentar
     * 
     * En Ticket:
     *   - Pago no se completó en tiempo
     * 
     * En PaymentProviderTicket:
     *   - Provider no respondió en tiempo
     *   - Limpieza automática de órdenes expiradas
     * 
     * Diferencia con FAILED:
     *   - EXPIRED = timeout, no hubo respuesta
     *   - FAILED = hubo respuesta pero negativa
     */
    const STATUS_EXPIRED = 'expired';
    
    /**
     * REFUNDED
     * 
     * Reembolsado (post-pago) 🔄
     * 
     * Principalmente usado en PaymentProviderTicket
     * 
     * En Ticket:
     *   - Entrada fue reembolsada
     * 
     * En PaymentProviderTicket:
     *   - Pago aprobado pero luego reembolsado
     *   - Usuario pidió devolución
     * 
     * IMPORTANTE:
     *   - Solo se usa DESPUÉS de COMPLETED
     *   - No es un estado inicial
     */
    const STATUS_REFUNDED = 'refunded';
    
    // ============================================================================
    // HELPERS Y CONVERSIONES
    // ============================================================================
    
    /**
     * Todos los estados válidos
     */
    public static function all(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
            self::STATUS_REFUNDED,
        ];
    }
    
    /**
     * Estados finales (no cambian más)
     */
    public static function final(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
            self::STATUS_REFUNDED,
        ];
    }
    
    /**
     * Estados en transición (pueden cambiar)
     */
    public static function transient(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
        ];
    }
    
    /**
     * Estados de éxito
     */
    public static function success(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_REFUNDED, // Fue completado primero
        ];
    }
    
    /**
     * Estados de fallo
     */
    public static function failure(): array
    {
        return [
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ];
    }
    
    /**
     * Convertir legacy states a nuevos
     * Útil para migration de datos históricos
     */
    public static function toLegacyString(string $legacyFormat): string
    {
        $map = [
            // Order legacy states
            'draft' => self::STATUS_PENDING,
            'reserved' => self::STATUS_PENDING,
            'payment_processing' => self::STATUS_PROCESSING,
            'paid' => self::STATUS_COMPLETED,
            'payment_failed' => self::STATUS_FAILED,
            
            // Ticket legacy states
            'pending_payment' => self::STATUS_PENDING,
            'processing' => self::STATUS_PROCESSING,
            'confirmed' => self::STATUS_COMPLETED,
            
            // PaymentProviderTicket legacy states
            'pending' => self::STATUS_PENDING,
            'queued' => self::STATUS_PROCESSING,
            'processing' => self::STATUS_PROCESSING,
            'approved' => self::STATUS_COMPLETED,
            'declined' => self::STATUS_FAILED,
            'failed' => self::STATUS_FAILED,
            'finalization_failed' => self::STATUS_FAILED,
        ];
        
        return $map[$legacyFormat] ?? self::STATUS_FAILED;
    }
    
    /**
     * Descripciones legibles
     */
    public static function label(string $status): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PROCESSING => 'En Proceso',
            self::STATUS_COMPLETED => 'Completado ✓',
            self::STATUS_FAILED => 'Fallido ✗',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_EXPIRED => 'Expirado',
            self::STATUS_REFUNDED => 'Reembolsado',
        ];
        
        return $labels[$status] ?? 'Desconocido';
    }
    
    /**
     * Colors para UI
     */
    public static function color(string $status): string
    {
        $colors = [
            self::STATUS_PENDING => 'yellow',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'orange',
            self::STATUS_EXPIRED => 'gray',
            self::STATUS_REFUNDED => 'purple',
        ];
        
        return $colors[$status] ?? 'gray';
    }
    
    /**
     * Validar que un estado es válido
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all());
    }
    
    /**
     * Validar transiciones permitidas
     * 
     * Ejemplo:
     *   PENDING puede pasar a: PROCESSING, CANCELLED, EXPIRED
     *   PROCESSING puede pasar a: COMPLETED, FAILED, EXPIRED, CANCELLED
     *   COMPLETED no puede pasar a nada (excepto REFUNDED)
     */
    public static function canTransitionTo(string $from, string $to): bool
    {
        $allowed = [
            self::STATUS_PENDING => [
                self::STATUS_PROCESSING,  // Normal flow
                self::STATUS_CANCELLED,   // User cancels
                self::STATUS_EXPIRED,     // Timeout
            ],
            self::STATUS_PROCESSING => [
                self::STATUS_COMPLETED,   // Payment approved
                self::STATUS_FAILED,      // Payment rejected
                self::STATUS_CANCELLED,   // User cancels
                self::STATUS_EXPIRED,     // Timeout
            ],
            self::STATUS_COMPLETED => [
                self::STATUS_REFUNDED,    // Post-payment refund
            ],
            self::STATUS_FAILED => [],    // Final state
            self::STATUS_CANCELLED => [], // Final state
            self::STATUS_EXPIRED => [],   // Final state
            self::STATUS_REFUNDED => [],  // Final state
        ];
        
        return in_array($to, $allowed[$from] ?? []);
    }
}
