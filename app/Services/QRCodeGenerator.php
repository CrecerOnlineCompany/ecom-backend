<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class QRCodeGenerator
{
    /**
     * Generar un código QR y devolver como Data URI (base64)
     * Usa phpqrcode local o fallback a API pública
     */
    public static function generateQRDataUri(string $data, int $size = 300): string
    {
        try {
            Log::info('QRCodeGenerator: Starting QR generation', [
                'data' => $data,
                'data_length' => strlen($data),
            ]);
            
            // Intentar con phpqrcode local
            $qrPath = self::generateQRImage($data);
            
            if (file_exists($qrPath)) {
                $qrContent = file_get_contents($qrPath);
                @unlink($qrPath);
                
                $base64 = base64_encode($qrContent);
                return 'data:image/png;base64,' . $base64;
            }
            
            throw new \Exception('QR image file not created');
            
        } catch (\Exception $e) {
            Log::warning('QRCodeGenerator: phpqrcode failed, using fallback API', [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: usar API pública qrserver.com
            return self::generateQRViaApi($data);
        }
    }

    /**
     * Generar un código QR y guardar como PNG temporal
     * Usa phpqrcode local
     */
    public static function generateQRImage(string $data): string
    {
        try {
            Log::info('QRCodeGenerator: generateQRImage() called', [
                'data' => $data,
                'data_length' => strlen($data),
            ]);
            
            // Incluir phpqrcode
            $phpqrcodeDir = base_path('phpqrcode');
            $phpqrcodeFile = $phpqrcodeDir . '/phpqrcode.php';
            
            if (!file_exists($phpqrcodeFile)) {
                throw new \Exception('phpqrcode.php not found at ' . $phpqrcodeFile);
            }
            
            require_once($phpqrcodeFile);
            
            // Crear directorio temporal si no existe
            $tmpDir = storage_path('tmp');
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            
            $tempPath = $tmpDir . '/qr_' . uniqid() . '.png';
            
            // Generar QR con phpqrcode
            \QRcode::png($data, $tempPath, 'L', 4, 4);
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Failed to generate QR image at ' . $tempPath);
            }
            
            $fileSize = filesize($tempPath);
            
            Log::info('QRCodeGenerator: QR generated successfully', [
                'path' => $tempPath,
                'data_length' => strlen($data),
                'file_size' => $fileSize,
            ]);
            
            return $tempPath;
            
        } catch (\Exception $e) {
            Log::error('QRCodeGenerator: Error generating QR image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generar un código QR y devolver como SVG
     * Usa API pública como fallback (no hay SVG nativo en phpqrcode simple)
     */
    public static function generateQRSvg(string $data): string
    {
        try {
            // Usar API pública que soporta SVG
            $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?format=svg&size=300x300&data=' . urlencode($data);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'CINEA-API',
                ]
            ]);
            
            $svg = @file_get_contents($apiUrl, false, $context);
            
            if ($svg === false) {
                throw new \Exception('Failed to fetch SVG from API');
            }
            
            Log::info('QRCodeGenerator: SVG generated via API');
            
            return $svg;
            
        } catch (\Exception $e) {
            Log::error('QRCodeGenerator: Error generating QR SVG', [
                'error' => $e->getMessage(),
            ]);
            
            // Retornar un placeholder simple
            return '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><text x="10" y="150">QR Error</text></svg>';
        }
    }

    /**
     * Fallback: Generar QR mediante API pública qrserver.com
     */
    private static function generateQRViaApi(string $data): string
    {
        try {
            // API endpoint: https://api.qrserver.com/v1/create-qr-code/?format=png&size=300x300&data=...
            $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?format=png&size=300x300&data=' . urlencode($data);
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'CINEA-API',
                ]
            ]);
            
            $pngData = @file_get_contents($apiUrl, false, $context);
            
            if ($pngData === false) {
                throw new \Exception('Failed to fetch QR from API');
            }
            
            $base64 = base64_encode($pngData);
            
            Log::info('QRCodeGenerator: QR generated via API fallback');
            
            return 'data:image/png;base64,' . $base64;
            
        } catch (\Exception $e) {
            Log::error('QRCodeGenerator: API fallback failed', [
                'error' => $e->getMessage(),
            ]);
            
            // Si todo falla, retornar un placeholder
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        }
    }
}
