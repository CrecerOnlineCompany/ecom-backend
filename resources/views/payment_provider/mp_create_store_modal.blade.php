<div class="form-group" style="margin-top: 20px; padding: 15px; background-color: #f8f8f8; border-radius: 4px;" data-mercado-pago-field="true">
    <label for="mp_create_store_token" style="font-weight: bold; margin-bottom: 10px; display: block;">
        🏬 Crear Sucursal (Store) en Mercado Pago
    </label>
    <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
        Requisitos principales: <strong>name</strong>, <strong>external_id</strong>, <strong>city_name</strong>, <strong>state_name</strong>, <strong>latitude</strong> y <strong>longitude</strong>.
    </p>

    <div class="row">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Access Token</label>
            <input type="password" id="mp_create_store_token" class="form-control" placeholder="APP_USR-xxxxxx...">
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">User ID</label>
            <input type="text" id="mp_create_store_user_id" class="form-control" placeholder="123456789">
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Nombre de Sucursal</label>
            <input type="text" id="mp_create_store_name" class="form-control" placeholder="Sucursal Centro">
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">External ID (Store)</label>
            <input type="text" id="mp_create_store_external_id" class="form-control" placeholder="SUC001">
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="col-md-8">
            <label style="font-size: 12px; color: #555;">Calle</label>
            <input type="text" id="mp_create_store_street_name" class="form-control" placeholder="Av. Siempre Viva">
        </div>
        <div class="col-md-4">
            <label style="font-size: 12px; color: #555;">Numero</label>
            <input type="text" id="mp_create_store_street_number" class="form-control" placeholder="1234">
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Ciudad</label>
            <input type="text" id="mp_create_store_city" class="form-control" placeholder="Buenos Aires">
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Provincia/Estado</label>
            <input type="text" id="mp_create_store_state" class="form-control" placeholder="Buenos Aires">
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Latitud</label>
            <input type="number" step="any" id="mp_create_store_lat" class="form-control" placeholder="-34.6037">
        </div>
        <div class="col-md-6">
            <label style="font-size: 12px; color: #555;">Longitud</label>
            <input type="number" step="any" id="mp_create_store_lng" class="form-control" placeholder="-58.3816">
        </div>
    </div>

    <div style="margin-top: 12px;">
        <button type="button" id="mp_create_store_btn" class="btn btn-success">
            <i class="fa fa-plus"></i> Crear Sucursal
        </button>
        <small style="display: inline-block; margin-left: 10px; color: #999;">
            ℹ️ El token no se guarda. Si esta vacio, se intentara usar el Access Token del formulario.
        </small>
    </div>
</div>

<div id="mp_create_store_result" style="display: none; margin-top: 15px;" data-mercado-pago-field="true"></div>

<div id="mp_create_store_loading" class="modal fade" tabindex="-1" role="dialog" style="display: none;">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 30px;">
                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #28a745;">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p style="margin-top: 15px; color: #666;">Creando sucursal en Mercado Pago...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('mp_create_store_btn');
    const result = document.getElementById('mp_create_store_result');
    const loadingModal = document.getElementById('mp_create_store_loading');

    if (!btn) return;

    btn.addEventListener('click', async function() {
        const tokenField = document.getElementById('mp_create_store_token');
        const formTokenField = document.querySelector('input[name="config[access_token]"]');
        const token = (tokenField?.value || '').trim() || (formTokenField?.value || '').trim();

        const userId = (document.getElementById('mp_create_store_user_id')?.value || '').trim();
        const name = (document.getElementById('mp_create_store_name')?.value || '').trim();
        const externalId = (document.getElementById('mp_create_store_external_id')?.value || '').trim();
        const streetName = (document.getElementById('mp_create_store_street_name')?.value || '').trim();
        const streetNumber = (document.getElementById('mp_create_store_street_number')?.value || '').trim();
        const cityName = (document.getElementById('mp_create_store_city')?.value || '').trim();
        const stateName = (document.getElementById('mp_create_store_state')?.value || '').trim();
        const latitude = (document.getElementById('mp_create_store_lat')?.value || '').trim();
        const longitude = (document.getElementById('mp_create_store_lng')?.value || '').trim();

        const missing = [];
        if (!token) missing.push('Access Token');
        if (!userId) missing.push('User ID');
        if (!name) missing.push('Nombre');
        if (!externalId) missing.push('External ID');
        if (!cityName) missing.push('Ciudad');
        if (!stateName) missing.push('Provincia/Estado');
        if (!latitude) missing.push('Latitud');
        if (!longitude) missing.push('Longitud');

        if (missing.length > 0) {
            alert('Faltan campos requeridos: ' + missing.join(', '));
            return;
        }

        if (loadingModal) {
            loadingModal.style.display = 'block';
            loadingModal.classList.add('show');
        }

        try {
            const response = await fetch('{{ route("admin.payment-providers.mp-create-store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                                   document.querySelector('input[name="_token"]')?.value || '',
                },
                body: JSON.stringify({
                    token,
                    user_id: userId,
                    name,
                    external_id: externalId,
                    street_name: streetName,
                    street_number: streetNumber,
                    city_name: cityName,
                    state_name: stateName,
                    latitude,
                    longitude,
                }),
            });

            const data = await response.json();

            if (loadingModal) {
                loadingModal.style.display = 'none';
                loadingModal.classList.remove('show');
            }

            if (response.ok && data.store) {
                const store = data.store;
                const storeId = store.id || store.store_id || 'N/A';
                const storeExternalId = store.external_id || externalId;

                result.innerHTML = `
                    <div class="alert alert-success">
                        <strong>✓ Sucursal creada:</strong>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li><strong>Store ID:</strong> ${storeId}</li>
                            <li><strong>External ID:</strong> ${storeExternalId}</li>
                            <li><strong>Nombre:</strong> ${store.name || name}</li>
                        </ul>
                        <em style="font-size: 12px; color: #666;">Se completo el campo Store ID en el formulario.</em>
                    </div>
                `;
                result.style.display = 'block';

                const storeIdField = document.querySelector('input[name="config[store_id]"]');
                if (storeIdField && storeId) {
                    storeIdField.value = storeId;
                    storeIdField.dispatchEvent(new Event('change', { bubbles: true }));
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
