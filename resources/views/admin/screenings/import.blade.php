@extends('admin::index')

@php
    $body_classes = '';
    $_user_="";
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4>Importar Funciones</h4>
                </div>
                <div class="card-body">
                    <form id="importForm" method="POST" action="{{ route('admin.screenings.import') }}" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="form-group mb-3">
                            <label>Selecciona archivo (Excel o CSV)</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                            <small class="form-text text-muted">Máximo 5MB. Formatos: .xlsx, .xls, .csv</small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-upload"></i> Importar
                            </button>
                            <a href="{{ route('admin.screenings.index') }}" class="btn btn-secondary">Cancelar</a>
                            <a href="{{ route('admin.screenings.export.excel') }}" class="btn btn-success float-end" target="_blank">
                                <i class="fa fa-download"></i> Descargar Template
                            </a>
                        </div>
                    </form>

                    <div id="result" style="margin-top: 20px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('importForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch(e.target.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        const data = await response.json();
        const resultDiv = document.getElementById('result');
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <strong>✅ Éxito!</strong> ${data.message}
                    <hr>
                    Creadas: ${data.results.created} | Actualizadas: ${data.results.updated} | Errores: ${data.results.failed}
                </div>
            `;
            
            if (data.results.errors.length > 0) {
                let html = '<div class="alert alert-warning"><strong>Errores encontrados:</strong><ul>';
                data.results.errors.forEach(err => {
                    html += `<li>Fila ${err.row}: ${err.message}</li>`;
                });
                html += '</ul></div>';
                resultDiv.innerHTML += html;
            }
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger"><strong>❌ Error!</strong> ${data.message}</div>`;
        }
    } catch (err) {
        document.getElementById('result').innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
    }
});
</script>
@endsection
