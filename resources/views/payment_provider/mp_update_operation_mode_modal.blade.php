<div class="form-group" style="margin-top: 20px; padding: 15px; background-color: #f8f8f8; border-radius: 4px;" data-mercado-pago-field="true">
    <label for="mp_update_op_mode_token" style="font-weight: bold; margin-bottom: 10px; display: block;">
        Actualizar modo de operacion de la terminal
    </label>
    <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
        Endpoint PATCH /terminals/v1/setup. Requiere enviar una lista de terminals con <strong>id</strong> y <strong>operating_mode</strong>.
    </p>

    <div class="row">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Access Token</label>
            <input type="password" id="mp_update_op_mode_token" class="form-control" placeholder="APP_USR-xxxxxx...">
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Terminal ID</label>
            <input type="text" id="mp_update_op_mode_terminal_id" class="form-control" placeholder="NEWLAND_N950__XXXXXXXX">
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Operating Mode</label>
            <select id="mp_update_op_mode_value" class="form-control">
                <option value="PDV">PDV</option>
                <option value="STANDALONE">STANDALONE</option>
            </select>
            <small style="display: block; margin-top: 6px; color: #999;">Valores esperados: PDV o STANDALONE.</small>
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Nota</label>
            <div style="padding: 7px 10px; background: #f1f1f1; border-radius: 4px; font-size: 12px; color: #666;">
                Solo algunas terminales soportan este cambio (por ejemplo NEWLAND_N950, INGENICO_MOVE2500, PAX_A910).
            </div>
        </div>
    </div>

    <div style="margin-top: 12px;">
        <button type="button" id="mp_update_op_mode_btn" class="btn btn-warning">
            <i class="fa fa-refresh"></i> Actualizar modo
        </button>
        <small style="display: inline-block; margin-left: 10px; color: #999;">
            El token no se guarda. Si esta vacio, se intentara usar el Access Token del formulario.
        </small>
    </div>
</div>

<div id="mp_update_op_mode_result" style="display: none; margin-top: 15px;" data-mercado-pago-field="true"></div>

<div id="mp_update_op_mode_loading" class="modal fade" tabindex="-1" role="dialog" style="display: none;">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 30px;">
                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #f0ad4e;">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p style="margin-top: 15px; color: #666;">Actualizando modo de operacion...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('mp_update_op_mode_btn');
    const result = document.getElementById('mp_update_op_mode_result');
    const loadingModal = document.getElementById('mp_update_op_mode_loading');

    if (!btn) return;

    btn.addEventListener('click', async function() {
        const tokenField = document.getElementById('mp_update_op_mode_token');
        const formTokenField = document.querySelector('input[name="config[access_token]"]');
        const token = (tokenField?.value || '').trim() || (formTokenField?.value || '').trim();

        const terminalIdField = document.getElementById('mp_update_op_mode_terminal_id');
        const terminalId = (terminalIdField?.value || '').trim() ||
            (document.querySelector('input[name="config[terminal_id]"]')?.value || '').trim();

        const operatingMode = document.getElementById('mp_update_op_mode_value')?.value || 'PDV';

        const missing = [];
        if (!token) missing.push('Access Token');
        if (!terminalId) missing.push('Terminal ID');

        if (missing.length > 0) {
            alert('Faltan campos requeridos: ' + missing.join(', '));
            return;
        }

        if (loadingModal) {
            loadingModal.style.display = 'block';
            loadingModal.classList.add('show');
        }

        try {
            const response = await fetch('{{ route("admin.payment-providers.mp-update-operation-mode") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                   document.querySelector('input[name="_token"]')?.value || '',
                },
                body: JSON.stringify({
                    token,
                    terminal_id: terminalId,
                    operating_mode: operatingMode,
                }),
            });

            let data = null;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error('Respuesta no JSON: ' + text.slice(0, 200));
            }

            if (loadingModal) {
                loadingModal.style.display = 'none';
                loadingModal.classList.remove('show');
            }

            if (response.ok && data.terminals) {
                const terminal = Array.isArray(data.terminals) ? data.terminals[0] : null;
                result.innerHTML = `
                    <div class="alert alert-success">
                        <strong>✓ Modo actualizado:</strong>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li><strong>Terminal:</strong> ${terminal?.id || terminalId}</li>
                            <li><strong>Modo:</strong> ${terminal?.operating_mode || operatingMode}</li>
                        </ul>
                        <em style="font-size: 12px; color: #666;">Reinicia la terminal si es necesario.</em>
                    </div>
                `;
                result.style.display = 'block';
            } else {
                const errorMsg = data.error || 'Error desconocido';
                const errorHint = data.hint || '';
                const errorCode = data.code || '';

                let errorHtml = `<div class="alert alert-danger"><strong>❌ Error:</strong> ${errorMsg}`;
                if (errorCode) {
                    errorHtml += `<br><small>Codigo: ${errorCode}</small>`;
                }
                if (errorHint) {
                    errorHtml += `<br><small>💡 ${errorHint}</small>`;
                }
                errorHtml += `</div>`;

                result.innerHTML = errorHtml;
                result.style.display = 'block';
            }
        } catch (error) {
            if (loadingModal) {
                loadingModal.style.display = 'none';
                loadingModal.classList.remove('show');
            }

            result.innerHTML = `
                <div class="alert alert-danger">
                    <strong>❌ Error de Conexión:</strong> ${error.message}
                </div>
            `;
            result.style.display = 'block';
        }
    });
});
</script>
