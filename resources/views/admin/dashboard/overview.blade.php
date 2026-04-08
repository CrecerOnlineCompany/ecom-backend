<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Accesos Directos</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($shortcuts as $shortcut)
                        <div class="col-md-4" style="margin-bottom: 12px;">
                            <a href="{{ $shortcut['route'] }}" class="btn {{ $shortcut['btn'] }} btn-block" style="text-align: left; padding: 12px 14px;">
                                <div style="font-size: 14px; font-weight: 600;">
                                    <i class="fa {{ $shortcut['icon'] }}"></i> {{ $shortcut['title'] }}
                                </div>
                                <div style="font-size: 12px; opacity: 0.9; margin-top: 4px;">
                                    {{ $shortcut['description'] }}
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Estadísticas Básicas</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
                        <div class="well" style="margin-bottom: 0;">
                            <div class="text-muted">Órdenes totales</div>
                            <div style="font-size: 24px; font-weight: 700;">{{ number_format($stats['orders_total']) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
                        <div class="well" style="margin-bottom: 0;">
                            <div class="text-muted">Órdenes pendientes/proceso</div>
                            <div style="font-size: 24px; font-weight: 700;">{{ number_format($stats['orders_pending']) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
                        <div class="well" style="margin-bottom: 0;">
                            <div class="text-muted">Órdenes completadas hoy</div>
                            <div style="font-size: 24px; font-weight: 700;">{{ number_format($stats['orders_completed_today']) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
                        <div class="well" style="margin-bottom: 0;">
                            <div class="text-muted">Entradas vendidas hoy</div>
                            <div style="font-size: 24px; font-weight: 700;">{{ number_format($stats['tickets_sold_today']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
                        <div class="well" style="margin-bottom: 0;">
                            <div class="text-muted">Entradas totales</div>
                            <div style="font-size: 24px; font-weight: 700;">{{ number_format($stats['tickets_total']) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
                        <div class="well" style="margin-bottom: 0;">
                            <div class="text-muted">Funciones activas hoy</div>
                            <div style="font-size: 24px; font-weight: 700;">{{ number_format($stats['screenings_today']) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
                        <div class="well" style="margin-bottom: 0;">
                            <div class="text-muted">Promociones activas</div>
                            <div style="font-size: 24px; font-weight: 700;">{{ number_format($stats['promotions_active']) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
                        <div class="well" style="margin-bottom: 0;">
                            <div class="text-muted">Actualizado</div>
                            <div style="font-size: 16px; font-weight: 600;">{{ now()->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                </div>
                <p class="text-muted" style="margin-top: 12px; margin-bottom: 0;">
                    Estas métricas usan consultas agregadas simples para mantener la carga baja.
                </p>
            </div>
        </div>
    </div>
</div>
