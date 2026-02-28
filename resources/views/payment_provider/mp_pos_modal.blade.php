<div class="form-group" style="margin-top: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 4px;" data-mercado-pago-field="true">
    <label for="mp_pos_token_input" style="font-weight: bold; margin-bottom: 10px; display: block;">
        🏪 Obtener POS desde Mercado Pago
    </label>
    <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
        Ingresa tu access token de Mercado Pago para traer automáticamente tus POS disponibles y seleccionar uno.
    </p>
    <div class="input-group">
        <input type="password" id="mp_pos_token_input" class="form-control" placeholder="APP_USR-xxxxxx...">
        <span class="input-group-btn">
            <button type="button" id="mp_fetch_pos" class="btn btn-primary" style="margin-left: 5px;">
                <i class="fa fa-download"></i> Obtener POS
            </button>
        </span>
    </div>
    <small style="display: block; margin-top: 8px; color: #999;">
        ℹ️ Tu token no se guardará, solo se usará para traer los POS disponibles.
    </small>
</div>

<!-- Contenedor para mostrar POS -->
<div id="mp_pos_container" style="display: none; margin-top: 20px;" data-mercado-pago-field="true">
    <div class="alert alert-info" style="border-radius: 4px;">
        <strong>✓ POS disponibles:</strong>
        <div id="mp_pos_list" style="margin-top: 10px;"></div>
    </div>
</div>

<!-- Modal de carga -->
<div id="mp_pos_loading_modal" class="modal fade" tabindex="-1" role="dialog" style="display: none;">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 30px;">
                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #007bff;">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p style="margin-top: 15px; color: #666;">Obteniendo POS de Mercado Pago...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fetchBtn = document.getElementById('mp_fetch_pos');
    const tokenInput = document.getElementById('mp_pos_token_input');
    const posContainer = document.getElementById('mp_pos_container');
    const posList = document.getElementById('mp_pos_list');
    const loadingModal = document.getElementById('mp_pos_loading_modal');
    
    if (!fetchBtn) return;

    fetchBtn.addEventListener('click', async function() {
        const token = tokenInput.value.trim();
        
        if (!token) {
            alert('Por favor ingresa un token válido');
            return;
        }

        // Mostrar modal de carga
        if (loadingModal) {
            loadingModal.style.display = 'block';
            loadingModal.classList.add('show');
        }

        try {
            const response = await fetch('{{ route("admin.payment-providers.mp-pos") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || 
                                   document.querySelector('input[name="_token"]')?.value || '',
                },
                body: JSON.stringify({ token: token }),
            });

            const data = await response.json();

            // Ocultar modal de carga
            if (loadingModal) {
                loadingModal.style.display = 'none';
                loadingModal.classList.remove('show');
            }

            if (response.ok && data.pos) {
                let pos = data.pos || [];
                
                // Asegurar que pos es un array
                if (!Array.isArray(pos)) {
                    pos = [];
                }
                
                if (pos.length === 0) {
                    posList.innerHTML = '<p class="text-muted">No se encontraron POS.</p>';
                } else {
                    let html = '<ul class="list-group">';
                    pos.forEach((item, index) => {
                        const posId = item.id || item.external_id || 'Desconocido';
                        const posName = item.name || item.merchant_name || posId;
                        const externalId = item.external_id || 'No disponible';
                        const storeId = item.store_id || 'No disponible';
                        const status = item.status || 'activo';
                        const qrUrl = item.qr?.image ? '✓ Disponible' : 'No disponible';
                        
                        html += `
                            <li class="list-group-item" style="padding: 12px; border-bottom: 1px solid #ddd; cursor: pointer;">
                                <div class="form-check" style="margin: 0;">
                                    <input class="form-check-input pos-checkbox" type="radio" 
                                           name="selected_pos"
                                           value="${externalId}" 
                                           id="pos_${posId}"
                                           data-pos-json='${JSON.stringify(item)}'>
                                    <label class="form-check-label" for="pos_${posId}" style="cursor: pointer; margin: 0; display: block;">
                                        <strong>${posName}</strong>
                                        <br>
                                        <small style="color: #999;">
                                            ID: ${posId} • External ID: ${externalId} • Store ID: ${storeId}
                                            <br>Estado: ${status} • QR: ${qrUrl}
                                        </small>
                                    </label>
                                </div>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    posList.innerHTML = html;
                    
                    // Agregar evento para seleccionar POS
                    document.querySelectorAll('.pos-checkbox').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            if (this.checked) {
                                const posData = JSON.parse(this.dataset.posJson);
                                fillPosData(posData);
                            }
                        });
                    });
                }
                posContainer.style.display = 'block';
            } else {
                const errorMsg = data.error || 'Error desconocido';
                const errorHint = data.hint || '';
                const errorCode = data.code || '';
                
                let errorHtml = `<div class="alert alert-danger" style="margin-top: 15px;">
                    <strong>❌ Error:</strong> ${errorMsg}`;
                
                if (errorCode) {
                    errorHtml += `<br><small>Código: ${errorCode}</small>`;
                }
                
                if (errorHint) {
                    errorHtml += `<br><small>💡 ${errorHint}</small>`;
                }
                
                if (errorCode === 403) {
                    errorHtml += `<br><small style="display: block; margin-top: 10px; color: #555;">
                        <strong>Posibles soluciones:</strong>
                        <ul style="margin: 5px 0;">
                            <li>Verifica que el token sea de una cuenta con permisos de administrador</li>
                            <li>Asegúrate de que la cuenta tenga POS configurados</li>
                            <li>El token podría haber expirado - intenta generar uno nuevo</li>
                        </ul>
                    </small>`;
                } else if (errorCode === 401) {
                    errorHtml += `<br><small style="display: block; margin-top: 10px; color: #555;">
                        El token no es válido o ha expirado. Genera un nuevo token en Mercado Pago.
                    </small>`;
                }
                
                errorHtml += `</div>`;
                posList.innerHTML = errorHtml;
                posContainer.style.display = 'block';
            }
        } catch (error) {
            // Ocultar modal de carga
            if (loadingModal) {
                loadingModal.style.display = 'none';
                loadingModal.classList.remove('show');
            }
            console.error('Error:', error);
            
            let errorHtml = `<div class="alert alert-danger" style="margin-top: 15px;">
                <strong>❌ Error de Conexión:</strong> ${error.message}
                <br><small style="display: block; margin-top: 10px; color: #555;">
                    Posibles causas:
                    <ul style="margin: 5px 0;">
                        <li>Token inválido o sin permisos</li>
                        <li>Problema de conexión con Mercado Pago</li>
                        <li>Token expirado - requiere uno nuevo</li>
                    </ul>
                </small>
            </div>`;
            posList.innerHTML = errorHtml;
            posContainer.style.display = 'block';
        }
    });

    // Permitir presionar Enter para buscar
    tokenInput.addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            fetchBtn.click();
        }
    });
});

/**
 * Llenar los datos del POS seleccionado en los campos del formulario
 */
function fillPosData(pos) {
    console.log('Llenando datos de POS:', pos);
    
    // Llenar External POS ID - OpenAdmin usa config[key] no config.key
    const externalPosIdField = document.querySelector('input[name="config[external_pos_id]"]');
    if (externalPosIdField) {
        externalPosIdField.value = pos.external_id || pos.id || '';
        externalPosIdField.dispatchEvent(new Event('change', { bubbles: true }));
        console.log('✓ External POS ID completado:', pos.external_id || pos.id);
    } else {
        console.warn('No se encontró el campo config[external_pos_id]');
    }
    
    // Llenar Store ID si está disponible
    const storeIdField = document.querySelector('input[name="config[store_id]"]');
    if (storeIdField && pos.store_id) {
        storeIdField.value = pos.store_id;
        storeIdField.dispatchEvent(new Event('change', { bubbles: true }));
        console.log('✓ Store ID completado:', pos.store_id);
    }
    
    // Mostrar mensaje con los datos llenados
    const successMsg = document.createElement('div');
    successMsg.className = 'alert alert-success';
    successMsg.style.marginTop = '15px';
    successMsg.innerHTML = `
        <strong>✓ POS seleccionado completado:</strong>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li><strong>POS ID:</strong> ${pos.id || 'N/A'}</li>
            <li><strong>External ID:</strong> ${pos.external_id || 'N/A'}</li>
            ${pos.name ? `<li><strong>Nombre:</strong> ${pos.name}</li>` : ''}
            ${pos.store_id ? `<li><strong>Store ID:</strong> ${pos.store_id}</li>` : ''}
            ${pos.status ? `<li><strong>Estado:</strong> ${pos.status}</li>` : ''}
        </ul>
        <em style="font-size: 12px; color: #666;">Completa los demás campos y haz clic en "Guardar" para aplicar los cambios.</em>
    `;
    
    // Insertar el mensaje después del contenedor de POS
    const container = document.getElementById('mp_pos_container');
    if (container) {
        const existingMsg = container.nextElementSibling;
        if (existingMsg && existingMsg.classList.contains('alert-success')) {
            existingMsg.remove();
        }
        container.parentElement.insertBefore(successMsg, container.nextElementSibling);
    }
    
    console.log('✓ Datos completados. El usuario debe guardar el formulario manualmente.');
}
</script>
