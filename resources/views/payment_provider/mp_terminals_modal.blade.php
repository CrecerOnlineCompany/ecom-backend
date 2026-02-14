<div class="form-group" style="margin-top: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 4px;" data-mercado-pago-field="true">
    <label for="mp_token_input" style="font-weight: bold; margin-bottom: 10px; display: block;">
        🔌 Obtener Terminales desde Mercado Pago
    </label>
    <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
        Ingresa tu access token de Mercado Pago para traer automáticamente tus terminales disponibles.
    </p>
    <div class="input-group">
        <input type="password" id="mp_token_input" class="form-control" placeholder="APP_USR-xxxxxx...">
        <span class="input-group-btn">
            <button type="button" id="mp_fetch_terminals" class="btn btn-primary" style="margin-left: 5px;">
                <i class="fa fa-download"></i> Obtener Terminales
            </button>
        </span>
    </div>
    <small style="display: block; margin-top: 8px; color: #999;">
        ℹ️ Tu token no se guardará, solo se usará para traer las terminales disponibles.
    </small>
</div>

<!-- Contenedor para mostrar terminales -->
<div id="mp_terminals_container" style="display: none; margin-top: 20px;" data-mercado-pago-field="true">
    <div class="alert alert-info" style="border-radius: 4px;">
        <strong>✓ Terminales disponibles:</strong>
        <div id="mp_terminals_list" style="margin-top: 10px;"></div>
    </div>
</div>

<!-- Modal de carga -->
<div id="mp_loading_modal" class="modal fade" tabindex="-1" role="dialog" style="display: none;">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 30px;">
                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color: #007bff;">
                    <span class="sr-only">Cargando...</span>
                </div>
                <p style="margin-top: 15px; color: #666;">Obteniendo terminales de Mercado Pago...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fetchBtn = document.getElementById('mp_fetch_terminals');
    const tokenInput = document.getElementById('mp_token_input');
    const terminalsContainer = document.getElementById('mp_terminals_container');
    const terminalsList = document.getElementById('mp_terminals_list');
    const loadingModal = document.getElementById('mp_loading_modal');
    
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
            const response = await fetch('{{ route("admin.payment-providers.mp-terminals") }}', {
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

            if (response.ok && data.terminals) {
                let terminals = data.terminals || [];
                
                // Asegurar que terminals es un array
                if (!Array.isArray(terminals)) {
                    terminals = [];
                }
                
                if (terminals.length === 0) {
                    terminalsList.innerHTML = '<p class="text-muted">No se encontraron terminales.</p>';
                } else {
                    let html = '<ul class="list-group">';
                    terminals.forEach((terminal, index) => {
                        const terminalId = terminal.id || terminal.terminal_id || 'Desconocido';
                        const terminalName = terminal.merchant_name || terminal.name || terminalId;
                        const terminalStatus = terminal.status || 'activo';
                        const storeId = terminal.store_id || 'No disponible';
                        const posId = terminal.pos_id || 'No disponible';
                        
                        html += `
                            <li class="list-group-item" style="padding: 12px; border-bottom: 1px solid #ddd; cursor: pointer;">
                                <div class="form-check" style="margin: 0;">
                                    <input class="form-check-input terminal-checkbox" type="radio" 
                                           name="selected_terminal"
                                           value="${terminalId}" 
                                           id="terminal_${terminalId}"
                                           data-terminal-json='${JSON.stringify(terminal)}'>
                                    <label class="form-check-label" for="terminal_${terminalId}" style="cursor: pointer; margin: 0; display: block;">
                                        <strong>${terminalName}</strong>
                                        <br>
                                        <small style="color: #999;">
                                            ID: ${terminalId} • Store ID: ${storeId} • POS ID: ${posId}
                                            <br>Estado: ${terminalStatus}
                                        </small>
                                    </label>
                                </div>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    terminalsList.innerHTML = html;
                    
                    // Agregar evento para seleccionar terminal
                    document.querySelectorAll('.terminal-checkbox').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            if (this.checked) {
                                const terminalData = JSON.parse(this.dataset.terminalJson);
                                fillTerminalData(terminalData);
                            }
                        });
                    });
                }
                terminalsContainer.style.display = 'block';
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
                            <li>Asegúrate de que la cuenta tenga terminales configuradas</li>
                            <li>El token podría haber expirado - intenta generar uno nuevo</li>
                        </ul>
                    </small>`;
                } else if (errorCode === 401) {
                    errorHtml += `<br><small style="display: block; margin-top: 10px; color: #555;">
                        El token no es válido o ha expirado. Genera un nuevo token en Mercado Pago.
                    </small>`;
                }
                
                errorHtml += `</div>`;
                terminalsList.innerHTML = errorHtml;
                terminalsContainer.style.display = 'block';
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
            terminalsList.innerHTML = errorHtml;
            terminalsContainer.style.display = 'block';
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
 * Llenar los datos de la terminal seleccionada en los campos del formulario
 */
function fillTerminalData(terminal) {
    console.log('Llenando datos de terminal:', terminal);
    
    // Llenar Terminal ID - OpenAdmin usa config[key] no config.key
    const terminalIdField = document.querySelector('input[name="config[terminal_id]"]');
    if (terminalIdField) {
        terminalIdField.value = terminal.id || '';
        terminalIdField.dispatchEvent(new Event('change', { bubbles: true }));
        console.log('✓ Terminal ID completado:', terminal.id);
    } else {
        console.warn('No se encontró el campo config[terminal_id]');
    }
    
    // Llenar Store ID
    const storeIdField = document.querySelector('input[name="config[store_id]"]');
    if (storeIdField) {
        storeIdField.value = terminal.store_id || '';
        storeIdField.dispatchEvent(new Event('change', { bubbles: true }));
        console.log('✓ Store ID completado:', terminal.store_id);
    } else {
        console.warn('No se encontró el campo config[store_id]');
    }
    
    // Mostrar mensaje con los datos llenados
    const successMsg = document.createElement('div');
    successMsg.className = 'alert alert-success';
    successMsg.style.marginTop = '15px';
    successMsg.innerHTML = `
        <strong>✓ Terminal seleccionada completada:</strong>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li><strong>ID Terminal:</strong> ${terminal.id || 'N/A'}</li>
            <li><strong>Store ID:</strong> ${terminal.store_id || 'N/A'}</li>
            ${terminal.pos_id ? `<li><strong>POS ID:</strong> ${terminal.pos_id}</li>` : ''}
            ${terminal.operating_mode ? `<li><strong>Modo:</strong> ${terminal.operating_mode}</li>` : ''}
        </ul>
        <em style="font-size: 12px; color: #666;">Completa los demás campos y haz clic en "Guardar" para aplicar los cambios.</em>
    `;
    
    // Insertar el mensaje después del contenedor de terminales
    const container = document.getElementById('mp_terminals_container');
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
