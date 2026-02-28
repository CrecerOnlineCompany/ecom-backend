<?php

namespace App\Admin\Controllers;

use App\Models\PaymentProvider;
use Illuminate\Support\Facades\Log;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class PaymentProviderController extends AdminController
{
    /**
     * Title for current resource
     */
    protected $title = 'Payment Providers';

    /**
     * Make a grid builder.
     */
    protected function grid()
    {
        $grid = new Grid(new PaymentProvider());

        $grid->column('id', __('admin.id'))->width(60);
        $grid->column('name', __('admin.name'));
        $grid->column('display_name', __('admin.display_name'));
        $grid->column('is_active', __('admin.status'))->bool()->width(80);
        
        // Columna de métodos disponibles
        $grid->column('methods', 'Métodos')->display(function () {
            $methods = $this->getAvailableMethods();
            if (empty($methods)) {
                return '<span style="color: #e74c3c;">No configurado</span>';
            }
            
            $badges = [
                'redirect' => '<span style="background: #3498db; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 3px;">Redirect</span>',
                'qr' => '<span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 3px;">QR</span>',
                'terminal' => '<span style="background: #2ecc71; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 3px;">Smart</span>',
            ];
            
            $html = '';
            foreach ($methods as $method) {
                $html .= $badges[$method] ?? '';
            }
            return $html ?: 'N/A';
        });
        
        $grid->column('requires_redirect', 'Redirect?')->bool()->width(80);
        $grid->column('supports_webhook', 'Webhook?')->bool()->width(80);

        return $grid;
    }

    /**
     * Make a show builder.
     */
    protected function detail($id)
    {
        $show = new Show(PaymentProvider::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('name', __('admin.name'));
        $show->field('display_name', __('admin.display_name'));
        $show->field('description', __('admin.description'));
        $show->field('icon_url', __('admin.icon_url'));
        $show->field('is_active', __('admin.status'))->bool();
        $show->field('requires_redirect', 'Requiere Redirect')->bool();
        $show->field('supports_webhook', 'Soporta Webhook')->bool();
        
        // Información de Configuración
        $show->panel('Información de Configuración', function ($panel) use ($show) {
            $provider = $show->getModel();
            $config = $provider->config ?? [];
            
            $panel->row(function ($row) use ($config) {
                $row->col(6)->field('config.access_token', 'Access Token')->value(
                    $config['access_token'] ? '***' . substr($config['access_token'], -10) : 'No configurado'
                );
                $row->col(6)->field('config.store_id', 'Store ID')->value($config['store_id'] ?? 'No configurado');
            });
            
            $panel->row(function ($row) use ($config) {
                $row->col(6)->field('config.currency_id', 'Moneda')->value($config['currency_id'] ?? 'ARS');
                $row->col(6)->field('config.terminal_id', 'Terminal ID')->value($config['terminal_id'] ?? 'No configurado');
            });
        });
        
        // Métodos de Pago Soportados
        $show->panel('Métodos de Pago', function ($panel) use ($show) {
            $provider = $show->getModel();
            $methods = $provider->getAvailableMethods();
            
            $html = '<div style="padding: 15px;">';
            foreach ($methods as $method) {
                $badges = [
                    'redirect' => '<span style="background: #3498db; color: white; padding: 5px 10px; border-radius: 3px; margin-right: 5px;">Redirección</span>',
                    'qr' => '<span style="background: #e74c3c; color: white; padding: 5px 10px; border-radius: 3px; margin-right: 5px;">QR</span>',
                    'terminal' => '<span style="background: #2ecc71; color: white; padding: 5px 10px; border-radius: 3px; margin-right: 5px;">Smart Point</span>',
                    'manual' => '<span style="background: #95a5a6; color: white; padding: 5px 10px; border-radius: 3px; margin-right: 5px;">Manual</span>',
                ];
                $html .= $badges[$method] ?? '';
            }
            $html .= '</div>';
            
            $panel->html($html);
        });

        $show->field('webhook_url', 'Webhook URL');
        $show->field('webhook_secret', 'Webhook Secret');
        $show->field('config', 'Configuración JSON Completa')->json();
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        return $show;
    }

    /**
     * Guardar un nuevo Payment Provider
     */
    /**
     * Guardar nuevo Payment Provider
     */
    public function store()
    {
        // Obtener la solicitud
        $request = request();
        
        Log::info('=== PAYMENT PROVIDER STORE ===');
        Log::info('Raw Request Data:', $request->all());
        
        // Procesar config si es Mercado Pago
        if (isset($request->name) && str_contains($request->name, 'mercado_pago')) {
            Log::info('✓ Mercado Pago detectado - procesando config');
            
            $configData = null;
            
            // Opción 1: config viene como array (OpenAdmin lo envía así)
            if (isset($request->config) && is_array($request->config)) {
                $configData = $request->config;
                Log::info('✓ Config recibido como ARRAY desde request:', $configData);
            } else {
                // Opción 2: config viene como campos individuales
                $configFields = [];
                foreach ($request->all() as $key => $value) {
                    if (str_starts_with($key, 'config[')) {
                        $fieldName = str_replace(['config[', ']'], '', $key);
                        $configFields[$fieldName] = $value;
                    }
                }
                if (!empty($configFields)) {
                    $configData = $configFields;
                    Log::info('✓ Config extraído de campos individuales:', $configFields);
                }
            }
            
            // Procesar y armar el config
            if ($configData) {
                $builtConfig = $this->buildConfigJson($configData);
                Log::info('Config JSON Armado en controller:', $builtConfig);
                Log::info('⚠ Nota: OpenAdmin ignorará merge(), el modelo lo reconstruirá en saving()');
            }
        }
        
        Log::info('=== FIN STORE ===');
        
        // Ejecutar parent::store y loguear el resultado
        $result = parent::store();
        
        // El result contiene la respuesta, pero extraemos el modelo de la solicitud
        $model = PaymentProvider::where('name', $request->name)->latest('id')->first();
        if ($model) {
            Log::info('=== POST-GUARDADO - Estado en BD ===');
            Log::info('Config guardado en BD:', ['config' => $model->config]);
            Log::info('=== FIN POST-GUARDADO ===');
        }
        
        return $result;
    }

    /**
     * Actualizar un Payment Provider existente
     */
    public function update($id)
    {
        // Obtener la solicitud
        $request = request();
        
        Log::info('=== PAYMENT PROVIDER UPDATE (ID: ' . $id . ') ===');
        Log::info('Raw Request Data:', $request->all());
        
        // Procesar config si es Mercado Pago
        if (isset($request->name) && str_contains($request->name, 'mercado_pago')) {
            Log::info('✓ Mercado Pago detectado - procesando config');
            
            $configData = null;
            
            // Opción 1: config viene como array (OpenAdmin lo envía así)
            if (isset($request->config) && is_array($request->config)) {
                $configData = $request->config;
                Log::info('✓ Config recibido como ARRAY desde request:', $configData);
            } else {
                // Opción 2: config viene como campos individuales
                $configFields = [];
                foreach ($request->all() as $key => $value) {
                    if (str_starts_with($key, 'config[')) {
                        $fieldName = str_replace(['config[', ']'], '', $key);
                        $configFields[$fieldName] = $value;
                    }
                }
                if (!empty($configFields)) {
                    $configData = $configFields;
                    Log::info('✓ Config extraído de campos individuales:', $configFields);
                }
            }
            
            // Procesar y armar el config
            if ($configData) {
                $builtConfig = $this->buildConfigJson($configData);
                Log::info('Config JSON Armado en controller:', $builtConfig);
                Log::info('⚠ Nota: OpenAdmin ignorará merge(), el modelo lo reconstruirá en saving()');
            }
        }
        
        Log::info('=== FIN UPDATE ===');
        
        // Ejecutar parent::update y loguear el resultado
        $result = parent::update($id);
        
        // Loguear qué quedó guardado en la BD
        $saved = PaymentProvider::find($id);
        Log::info('=== POST-GUARDADO - Estado en BD ===');
        Log::info('Config guardado en BD:', ['config' => $saved->config]);
        Log::info('=== FIN POST-GUARDADO ===');
        
        return $result;
    }
    
    /**
     * Campos que deben ser booleanos en el config
     */
    private $booleanConfigFields = [
        'supports_redirect',
        'supports_qr',
        'supports_terminal',
        'auto_send',
    ];

    /**
     * Construir el JSON de config con lógica completa
     */
    private function buildConfigJson(array $configFields): array
    {
        $config = PaymentProvider::normalizeConfig($configFields);
        Log::info('Config normalizado en buildConfigJson:', $config);
        
        return $config;
    }

    /**
     * Make a form builder.
     */
    protected function form()
    {
        $form = new Form(new PaymentProvider());
        $paymentProviderId = request()->route('payment_provider');
        $uniqueRule = 'required|unique:payment_providers,name';
        if ($paymentProviderId) {
            $uniqueRule .= ',' . $paymentProviderId;
        }

        $form->text('name', __('admin.name'))->rules($uniqueRule);
        $form->text('display_name', __('admin.display_name'))->rules('required');
        $form->textarea('description', __('admin.description'));
        $form->url('icon_url', __('admin.icon_url'));
        $form->switch('is_active', __('admin.status'))->default(1);
        $form->switch('requires_redirect', 'Requiere Redirect')->default(0);
        $form->switch('supports_webhook', 'Soporta Webhook')->default(1);

        // Sección: Autenticación Mercado Pago (Solo para Mercado Pago)
        $form->divider('Configuración Mercado Pago')
            ->attribute('data-mercado-pago-section', 'true')
            ->attribute('style', 'display: none;');
        
        $form->text('config.access_token', 'Access Token')
            ->placeholder('APP_USR-xxxxxxx')
            ->attribute('data-mercado-pago-field', 'true')
            ->help('<strong>REQUERIDO</strong> para usar Mercado Pago. Token de acceso debe tener permisos de pago y dominio.');
        
        // Botón para obtener terminales
        $form->html($this->getMercadoPagoTerminalsModal());
        
        // Sección: Configuración POS
        $form->divider('Configuración POS')
            ->attribute('data-mercado-pago-section', 'true')
            ->attribute('style', 'display: none;');
        
        $form->text('config.external_pos_id', 'External POS ID')
            ->placeholder('Ej: POS_002 o external_id del POS')
            ->attribute('data-mercado-pago-field', 'true')
            ->help('ID externo único del POS para identificarlo en Mercado Pago. <strong>REQUERIDO</strong> para modo de QR estático.');
        
        // Botón para obtener POS
        $form->html($this->getMercadoPagoPoSModal());
        
        $form->text('config.store_id', 'Store ID / POS ID')
            ->placeholder('Ej: 123456')
            ->attribute('data-mercado-pago-field', 'true')
            ->help('<strong>REQUERIDO</strong> para usar Mercado Pago. Identificador único de tu sucursal o punto de venta.');
        
        $form->text('config.currency_id', 'ID Moneda')
            ->default('ARS')
            ->placeholder('ARS, USD, BRL, etc')
            ->attribute('data-mercado-pago-field', 'true')
            ->help('Moneda a usar para los pagos. Por defecto: ARS');
        
        // Sección: Métodos de Pago Disponibles (Solo para Mercado Pago)
        $form->divider('Métodos de Pago Disponibles')
            ->attribute('data-mercado-pago-section', 'true')
            ->attribute('style', 'display: none;');
        
        $form->switch('config.supports_redirect', 'Pago por Redirección')
            ->default(1)
            ->help('Redirige al usuario a Mercado Pago para completar el pago. <strong>Requiere:</strong> Access Token + Store ID')
            ->attribute('data-mercado-pago-field', 'true');
        
        $form->switch('config.supports_qr', 'Pago por QR')
            ->default(1)
            ->help('Genera código QR que el cliente puede escanear. <strong>Requiere:</strong> Access Token + Store ID + Tipo de QR')
            ->attribute('data-mercado-pago-field', 'true');
        
        $form->switch('config.supports_terminal', 'Pago por Terminal Smart Punto')
            ->default(1)
            ->help('Envía la orden a una terminal POS física para procesamiento. <strong>Requiere:</strong> Access Token + Store ID + Terminal ID')
            ->attribute('data-mercado-pago-field', 'true');

        // Sección: Configuración QR (Solo para Mercado Pago)
        $form->divider('Configuración QR (opcional)')
            ->attribute('data-mercado-pago-section', 'true')
            ->attribute('style', 'display: none;');
        
        $form->select('config.qr_type', 'Tipo de QR', [
            'native' => 'QR Nativo (Recomendado)',
            'custom' => 'QR Personalizado',
        ])
            ->default('native')
            ->help('Tipo de código QR a generar. <strong>REQUERIDO</strong> si "Pago por QR" está habilitado.')
            ->attribute('data-mercado-pago-field', 'true');

        // Sección: Configuración Terminal Smart (Solo para Mercado Pago)
        $form->divider('Configuración Terminal Smart Punto')
            ->attribute('data-mercado-pago-section', 'true')
            ->attribute('style', 'display: none;');
        
        $form->text('config.terminal_id', 'ID Terminal')
            ->placeholder('Ej: PAX_A910__SMARTPOS1494545656')
            ->help('ID de la terminal. Este campo es REQUERIDO si "Pago por Terminal Smart" está habilitado.')
            ->attribute('data-mercado-pago-field', 'true');
        
        $form->switch('config.auto_send', 'Envío Automático a Terminal')
            ->help('Si está habilitado, la orden se enviará automáticamente a la terminal POS.')
            ->default(1)
            ->attribute('data-mercado-pago-field', 'true');

        // JavaScript para mostrar/ocultar campos de Mercado Pago
        $form->html($this->getMercadoPagoScript());

        return $form;
    }
    
    /**
     * Modal HTML para obtener terminales de Mercado Pago
     */
    private function getMercadoPagoTerminalsModal(): string
    {
        return view('payment_provider.mp_terminals_modal')->render();
    }

    /**
     * Modal HTML para obtener POS de Mercado Pago
     */
    private function getMercadoPagoPoSModal(): string
    {
        return view('payment_provider.mp_pos_modal')->render();
    }


    /**
     * Generar script JavaScript para mostrar/ocultar campos
     */
    private function getMercadoPagoScript(): string
    {
        return <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameField = document.querySelector('input[name="name"]');
    const mpFields = document.querySelectorAll('[data-mercado-pago-field="true"]');
    const mpSections = document.querySelectorAll('[data-mercado-pago-section="true"]');
    
    function toggleMercadoPagoFields() {
        const isMercadoPago = nameField && nameField.value.toLowerCase().includes('mercado_pago');
        
        mpFields.forEach(field => {
            const wrapper = field.closest('.form-group') || field.parentElement;
            if (wrapper) {
                wrapper.style.display = isMercadoPago ? '' : 'none';
            }
        });
        
        mpSections.forEach(section => {
            section.style.display = isMercadoPago ? '' : 'none';
        });
    }
    
    // Ejecutar al cargar
    toggleMercadoPagoFields();
    
    // Ejecutar cuando cambia el nombre
    if (nameField) {
        nameField.addEventListener('change', toggleMercadoPagoFields);
        nameField.addEventListener('input', toggleMercadoPagoFields);
    }
});
</script>
HTML;
    }

    /**
     * Obtener terminales de Mercado Pago
     */
    public function getMercadoPagoTerminals()
    {
        $token = request()->input('token');
        
        if (!$token) {
            return response()->json(['error' => 'Token requerido'], 400);
        }

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
            ]);
            
            // Intentar con versiones diferentes del endpoint
            $endpoints = [
                'https://api.mercadopago.com/v1/pos',
                'https://api.mercadopago.com/terminals/v1/list',
                'https://api.mercadopago.com/terminals',
            ];
            
            $response = null;
            $lastError = null;
            
            foreach ($endpoints as $endpoint) {
                try {
                    // Intentar con GET primero
                    $response = $client->request('GET', $endpoint, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $token,
                            'Content-Type' => 'application/json',
                            'User-Agent' => 'Cinelar Admin',
                        ],
                        'http_errors' => false, // No lanzar excepciones en errores HTTP
                    ]);
                    
                    $statusCode = $response->getStatusCode();
                    
                    // Si obtiene una respuesta exitosa (2xx), romper el loop
                    if ($statusCode >= 200 && $statusCode < 300) {
                        break;
                    } elseif ($statusCode === 403) {
                        $lastError = 'Token sin permisos para acceder a terminales (403)';
                    } elseif ($statusCode === 401) {
                        $lastError = 'Token inválido o expirado (401)';
                    } elseif ($statusCode === 404) {
                        $lastError = 'Endpoint no encontrado (404)';
                    } else {
                        $lastError = "Error HTTP $statusCode";
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    continue;
                }
            }
            
            if (!$response || $response->getStatusCode() >= 400) {
                return response()->json([
                    'error' => $lastError ?? 'No se pudo obtener terminales de Mercado Pago',
                    'hint' => 'Verifica que el token sea válido y tenga permisos suficientes',
                    'code' => $response ? $response->getStatusCode() : 'unknown',
                ], 403);
            }
            
            $data = json_decode($response->getBody(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Respuesta inválida de Mercado Pago',
                    'hint' => 'La respuesta no es JSON válido',
                ], 500);
            }
            
            // Extraer terminales de varias estructuras posibles
            $terminals = [];
            
            if (!empty($data['terminals']['data']['terminals'])) {
                $terminals = $data['terminals']['data']['terminals'];
            } elseif (!empty($data['terminals'])) {
                $terminals = $data['terminals'];
            } elseif (!empty($data['data']['terminals'])) {
                $terminals = $data['data']['terminals'];
            } elseif (!empty($data['data']) && is_array($data['data'])) {
                // Si es un array directo de terminales (endpoint /pos)
                $terminals = $data['data'];
            } elseif (is_array($data) && count($data) > 0 && isset($data[0]['id'])) {
                // Si arrays directamente con estructura de terminal
                $terminals = $data;
            }
            
            return response()->json([
                'status' => 'success',
                'terminals' => $terminals,
                'count' => count($terminals),
            ]);
        } catch (\Exception $e) {
            Log::error('Error obtaining MP terminals:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'error' => 'Error al obtener terminales: ' . $e->getMessage(),
                'hint' => 'Revisa los logs para más detalles',
            ], 500);
        }
    }

    /**
     * Obtener POS de Mercado Pago
     */
    public function getMercadoPagoPos()
    {
        $token = request()->input('token');
        
        if (!$token) {
            return response()->json(['error' => 'Token requerido'], 400);
        }

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
            ]);
            
            // Endpoint para obtener POS
            $endpoint = 'https://api.mercadopago.com/pos';
            
            try {
                $response = $client->request('GET', $endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'Cinelar Admin',
                    ],
                    'http_errors' => false,
                    'query' => [
                        'limit' => 100,
                    ]
                ]);
                
                $statusCode = $response->getStatusCode();
                
                if ($statusCode >= 400) {
                    $errorMsg = match($statusCode) {
                        403 => 'Token sin permisos para acceder a POS (403)',
                        401 => 'Token inválido o expirado (401)',
                        404 => 'Endpoint no encontrado (404)',
                        default => "Error HTTP $statusCode",
                    };
                    
                    return response()->json([
                        'error' => $errorMsg,
                        'hint' => 'Verifica que el token sea válido y tenga permisos suficientes',
                        'code' => $statusCode,
                    ], 403);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Error de conexión: ' . $e->getMessage(),
                    'hint' => 'Revisa tu conexión y que el token sea válido',
                ], 500);
            }
            
            $data = json_decode($response->getBody(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Respuesta inválida de Mercado Pago',
                    'hint' => 'La respuesta no es JSON válido',
                ], 500);
            }
            
            // Extraer POS de varias estructuras posibles
            $posList = [];
            
            if (!empty($data['results'])) {
                $posList = $data['results'];
            } elseif (!empty($data['pos'])) {
                $posList = $data['pos'];
            } elseif (is_array($data) && count($data) > 0) {
                // Si es un array directo de POS
                $posList = $data;
            }
            
            return response()->json([
                'status' => 'success',
                'pos' => $posList,
                'count' => count($posList),
            ]);
        } catch (\Exception $e) {
            Log::error('Error obtaining MP POS:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'error' => 'Error al obtener POS: ' . $e->getMessage(),
                'hint' => 'Revisa los logs para más detalles',
            ], 500);
        }
    }
}
