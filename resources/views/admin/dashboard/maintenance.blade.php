<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Mantenimiento</h3>
            </div>
            <div class="card-body">
                @if(session('maintenance_success'))
                    <div class="alert alert-success">
                        {{ session('maintenance_success') }}
                    </div>
                @endif

                @if(session('maintenance_error'))
                    <div class="alert alert-danger">
                        {{ session('maintenance_error') }}
                    </div>
                @endif

                <p class="text-muted">
                    Ejecuta manualmente la regeneración de tickets para órdenes con asientos reservados y sin tickets.
                </p>

                <form method="POST" action="{{ route('admin.maintenance.regenerate-order-tickets') }}">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        Ejecutar regeneración de tickets
                    </button>
                </form>

                @if(session('maintenance_output'))
                    <hr>
                    <p><strong>Salida del comando</strong></p>
                    <pre style="max-height: 300px; overflow: auto;">{{ session('maintenance_output') }}</pre>
                @endif
            </div>
        </div>
    </div>
</div>
