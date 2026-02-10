<?php

namespace App\Console\Commands;

use App\Models\PaymentProvider;
use Illuminate\Console\Command;

class FixPaymentProviderConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:fix-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Arreglar la configuración de proveedores de pago (convierte strings JSON a arrays)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Escaneando proveedores de pago...\n');

        $providers = PaymentProvider::all();

        foreach ($providers as $provider) {
            $this->line("Provider: {$provider->name}");
            
            $raw = $provider->getRawOriginal('config');
            $this->line("  Raw type: " . gettype($raw));
            $this->line("  Raw value: " . substr($raw, 0, 50) . (strlen($raw) > 50 ? '...' : ''));

            // Intenta decodificar
            if (is_string($raw)) {
                try {
                    $decoded = json_decode($raw, true);
                    if ($decoded === null && $raw !== 'null') {
                        $this->error("  ❌ JSON inválido");
                        continue;
                    }
                    
                    $this->line("  ✅ JSON válido decodificado:");
                    foreach ($decoded as $key => $value) {
                        if (is_string($value) && strlen($value) > 50) {
                            $this->line("    - {$key}: " . substr($value, 0, 40) . "...");
                        } else {
                            $this->line("    - {$key}: {$value}");
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Error decodificando: " . $e->getMessage());
                }
            } else {
                $this->info("  ℹ️  Ya es array (cast funcionó)");
            }
            
            // Verificar access_token
            $token = $provider->config['access_token'] ?? null;
            if ($token) {
                $this->line("  ✅ access_token presente: " . substr($token, 0, 20) . "...");
            } else {
                $this->warn("  ⚠️  Sin access_token");
            }

            $this->line("");
        }

        $this->info("✅ Escaneo completado");
    }
}
