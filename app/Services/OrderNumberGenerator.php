<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

class OrderNumberGenerator
{
    /**
     * Genera un número de orden único y legible
     * Formato: ORD-YYYYMMDD-XXXXX (donde XXXXX es secuencial + check digit)
     *
     * @return string
     */
    public static function generate(): string
    {
        $date = now()->format('Ymd');
        $today_count = Order::whereDate('created_at', now()->startOfDay())
            ->count();
        
        // Secuencial del día (4 dígitos)
        $sequence = str_pad($today_count + 1, 4, '0', STR_PAD_LEFT);
        
        // Generar check digit (suma de dígitos, módulo 10)
        $check_digit = self::calculateCheckDigit($date . $sequence);
        
        return sprintf('ORD-%s-%s%d', $date, $sequence, $check_digit);
    }

    /**
     * Calcula un dígito de verificación simple basado en suma de dígitos
     * Útil para validaciones simples
     *
     * @param string $input
     * @return int
     */
    public static function calculateCheckDigit(string $input): int
    {
        $sum = array_sum(str_split($input));
        return $sum % 10;
    }

    /**
     * Valida que un order_number tenga el formato y check digit correcto
     *
     * @param string $order_number
     * @return bool
     */
    public static function validate(string $order_number): bool
    {
        // Validar formato: ORD-YYYYMMDD-XXXX[digit]
        if (!preg_match('/^ORD-(\d{8})-(\d{4})(\d)$/', $order_number, $matches)) {
            return false;
        }

        $date_part = $matches[1];
        $sequence = $matches[2];
        $provided_check = (int)$matches[3];

        // Validar check digit
        $calculated_check = self::calculateCheckDigit($date_part . $sequence);
        
        return $provided_check === $calculated_check;
    }
}
