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
                    <h4>Crear funciones semanales</h4>
                    <div class="text-muted">{{ $movie->title }}</div>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Hay errores en el formulario</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('weekly_screenings_result'))
                        @php
                            $result = session('weekly_screenings_result');
                        @endphp
                        <div class="alert alert-success">
                            <strong>Funciones creadas:</strong> {{ $result['created'] ?? 0 }} |
                            <strong>Omitidas por existir:</strong> {{ $result['skipped'] ?? 0 }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.movies.weekly-screenings.store', ['movie' => $movie->id]) }}">
                        @csrf

                        <div class="form-group mb-3">
                            <label>Sala</label>
                            <select class="form-control" name="room_id" required>
                                <option value="">Selecciona una sala</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>
                                        {{ optional($room->cinema)->name ?? 'Sin cine' }} - {{ $room->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label>Desde</label>
                            <input type="date" class="form-control" name="start_date" value="{{ old('start_date') }}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label>Hasta</label>
                            <input type="date" class="form-control" name="end_date" value="{{ old('end_date') }}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label>Días de la semana</label>
                            <div class="d-flex flex-wrap gap-2">
                                @php
                                    $weekdayLabels = [
                                        1 => 'Lunes',
                                        2 => 'Martes',
                                        3 => 'Miércoles',
                                        4 => 'Jueves',
                                        5 => 'Viernes',
                                        6 => 'Sábado',
                                        0 => 'Domingo',
                                    ];
                                    $oldWeekdays = old('weekdays', []);
                                @endphp
                                @foreach ($weekdayLabels as $value => $label)
                                    <label class="me-3">
                                        <input type="checkbox" name="weekdays[]" value="{{ $value }}"
                                            @checked(in_array((string) $value, (array) $oldWeekdays, true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label>Horario</label>
                            <input type="time" class="form-control" name="start_time" value="{{ old('start_time') }}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label>Precio</label>
                            <input type="text" class="form-control" name="price" value="{{ old('price') }}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label>Formato</label>
                            <select class="form-control" name="format" required>
                                @foreach (['2D','3D','IMAX','4DX'] as $format)
                                    <option value="{{ $format }}" @selected(old('format', '2D') === $format)>{{ $format }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label>Idioma</label>
                            <select class="form-control" name="language" required>
                                @foreach ($language_options as $value => $label)
                                    <option value="{{ $value }}" @selected(old('language', array_key_first($language_options)) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label>Activa</label>
                            <select class="form-control" name="is_active" required>
                                <option value="1" @selected(old('is_active', '1') == '1')>Sí</option>
                                <option value="0" @selected(old('is_active') == '0')>No</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-calendar"></i> Crear funciones
                            </button>
                            <a href="{{ route('admin.movies.index') }}" class="btn btn-secondary">Volver</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
