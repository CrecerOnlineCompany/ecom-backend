<div class="form-group" style="margin-top: 20px; padding: 15px; background-color: #f8f8f8; border-radius: 4px;" data-mercado-pago-field="true">
    <label for="mp_create_pos_token" style="font-weight: bold; margin-bottom: 10px; display: block;">
        🧾 Crear POS en Mercado Pago
    </label>
    <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
        Requisitos principales: <strong>name</strong>, <strong>fixed_amount</strong>, <strong>store_id</strong>, <strong>external_store_id</strong>, <strong>external_id</strong> y <strong>category</strong>.
    </p>

    <div class="row">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Access Token</label>
            <input type="password" id="mp_create_pos_token" class="form-control" placeholder="APP_USR-xxxxxx...">
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Nombre del POS</label>
            <input type="text" id="mp_create_pos_name" class="form-control" placeholder="Caja 1">
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Store ID</label>
            <input type="text" id="mp_create_pos_store_id" class="form-control" placeholder="123456">
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">External Store ID</label>
            <input type="text" id="mp_create_pos_external_store_id" class="form-control" placeholder="SUC001">
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">External POS ID</label>
            <input type="text" id="mp_create_pos_external_id" class="form-control" placeholder="POS_001">
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Fixed Amount</label>
            <select id="mp_create_pos_fixed_amount" class="form-control">
                <option value="true" selected>Si</option>
                <option value="false">No</option>
            </select>
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Categoria (MCC) - requerido</label>
            <input type="text" id="mp_create_pos_category" class="form-control" placeholder="621102">
            <small style="display: block; margin-top: 6px; color: #999;">Consulta la lista de MCC en la API Reference de Mercado Pago.</small>
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Sugerencia</label>
            <div style="padding: 7px 10px; background: #f1f1f1; border-radius: 4px; font-size: 12px; color: #666;">
                Usa el Store ID del formulario o crea una sucursal primero.
            </div>
        </div>
    </div>

    <div style="margin-top: 12px;">
        <button type="button" id="mp_create_pos_btn" class="btn btn-success">
            <i class="fa fa-plus"></i> Crear POS
        </button>
        <small style="display: inline-block; margin-left: 10px; color: #999;">
            ℹ️ El token no se guarda. Si esta vacio, se intentara usar el Access Token del formulario.
        </small>
    </div>
</div>

<div id="mp_create_pos_result" style="display: none; margin-top: 15px;" data-mercado-pago-field="true"></div>

<div id="mp_create_pos_loading" class="modal fade" tabindex="-1" role="dialog" style="display: none;">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 30px;">
                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #28a745;">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p style="margin-top: 15px; color: #666;">Creando POS en Mercado Pago...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('mp_create_pos_btn');
    const result = document.getElementById('mp_create_pos_result');
    const loadingModal = document.getElementById('mp_create_pos_loading');

    if (!btn) return;

    btn.addEventListener('click', async function() {
        const tokenField = document.getElementById('mp_create_pos_token');
        const formTokenField = document.querySelector('input[name="config[access_token]"]');
        const token = (tokenField?.value || '').trim() || (formTokenField?.value || '').trim();

        const name = (document.getElementById('mp_create_pos_name')?.value || '').trim();
        const storeIdField = document.getElementById('mp_create_pos_store_id');
        const storeId = (storeIdField?.value || '').trim() ||
            (document.querySelector('input[name="config[store_id]"]')?.value || '').trim();

        const externalStoreId = (document.getElementById('mp_create_pos_external_store_id')?.value || '').trim();
        const externalId = (document.getElementById('mp_create_pos_external_id')?.value || '').trim();
        const fixedAmount = document.getElementById('mp_create_pos_fixed_amount')?.value || 'true';
        const category = (document.getElementById('mp_create_pos_category')?.value || '').trim();

        const missing = [];
        if (!token) missing.push('Access Token');
        if (!name) missing.push('Nombre');
        if (!storeId) missing.push('Store ID');
        if (!externalStoreId) missing.push('External Store ID');
        if (!externalId) missing.push('External POS ID');
        if (!category) missing.push('Categoria (MCC)');

        if (missing.length > 0) {
            alert('Faltan campos requeridos: ' + missing.join(', '));
            return;
        }

        if (loadingModal) {
            loadingModal.style.display = 'block';
            loadingModal.classList.add('show');
        }

        try {
            const response = await fetch('{{ route("admin.payment-providers.mp-create-pos") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                   document.querySelector('input[name="_token"]')?.value || '',
                },
                body: JSON.stringify({
                    token,
                    name,
                    fixed_amount: fixedAmount === 'true',
                    store_id: storeId,
                    external_store_id: externalStoreId,
                    external_id: externalId,
                    category,
                }),
            });

            const data = await response.json();

            if (loadingModal) {
                loadingModal.style.display = 'none';
                loadingModal.classList.remove('show');
            }

            if (response.ok && data.pos) {
                const pos = data.pos;
                const posId = pos.id || pos.pos_id || 'N/A';
                const posExternalId = pos.external_id || externalId;

                result.innerHTML = `
                    <div class="alert alert-success">
                        <strong>✓ POS creado:</strong>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li><strong>POS ID:</strong> ${posId}</li>
                            <li><strong>External ID:</strong> ${posExternalId}</li>
                            <li><strong>Nombre:</strong> ${pos.name || name}</li>
                        </ul>
                        <em style="font-size: 12px; color: #666;">Se completo el External POS ID en el formulario.</em>
                    </div>
                `;
                result.style.display = 'block';

                const externalPosField = document.querySelector('input[name="config[external_pos_id]"]');
                if (externalPosField && posExternalId) {
                    externalPosField.value = posExternalId;
                    externalPosField.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const storeIdFieldForm = document.querySelector('input[name="config[store_id]"]');
                if (storeIdFieldForm && storeId) {
                    storeIdFieldForm.value = storeId;
                    storeIdFieldForm.dispatchEvent(new Event('change', { bubbles: true }));
                }
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
