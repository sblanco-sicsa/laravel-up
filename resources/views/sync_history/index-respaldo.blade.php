@extends('layouts.app')

@section('content')
    <div class="container">
        <h4 class="mb-4 text-center fw-semibold">
            <i class="bi bi-clock-history"></i> Historial de Sincronizaciones
        </h4>

        <div class="table-responsive">
            <table id="tabla-sync" class="table table-bordered table-hover table-sm align-middle text-center small">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th><i class="bi bi-plus-circle text-success"></i></th>
                        <th><i class="bi bi-arrow-repeat text-primary"></i></th>
                        <th><i class="bi bi-arrow-repeat"></i></th>
                        <th><i class="bi bi-exclamation-circle text-danger"></i></th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sincronizaciones as $sync)
                        <tr>
                            <td>{{ $sync->id }}</td>
                            <td>{{ $sync->cliente }}</td>
                            <td>{{ \Carbon\Carbon::parse($sync->started_at)->format('d/m/Y H:i:s') }}</td>
                            <td>{{ \Carbon\Carbon::parse($sync->finished_at)->format('d/m/Y H:i:s') }}</td>
                            <td class="text-success fw-bold">{{ $sync->total_creados }}</td>
                            <td class="text-primary fw-bold">{{ $sync->total_actualizados }}</td>
                            <td class="text-muted">{{ $sync->total_omitidos }}</td>
                            <td class="text-danger">{{ $sync->total_fallidos_categoria }}</td>
                            <td class="text-nowrap" style="width: 1%; white-space: nowrap;">
                                <div class="d-flex align-items-center gap-1">
                                    <button class="btn btn-info btn-sm px-2 py-1" data-bs-toggle="collapse"
                                        data-bs-target="#detalles-{{ $sync->id }}">
                                        <i class="bi bi-eye"></i> Ver
                                    </button>

                                    <a href="{{ route('sync.stock_cero', $sync->id) }}"
                                        class="btn btn-warning btn-sm px-2 py-1 {{ !$sync->has_stock_cero_csv ? 'disabled' : '' }}"
                                        @if(!$sync->has_stock_cero_csv) aria-disabled="true" tabindex="-1" @endif>
                                        <i class="bi bi-file-earmark-spreadsheet"></i> Stock = 0
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr class="collapse bg-light" id="detalles-{{ $sync->id }}">
                            <td colspan="9">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered small">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th>SKU</th>
                                                <th>Tipo</th>
                                                <th style="width: 45%">Antes</th>
                                                <th style="width: 45%">Después</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($sync->detalles as $detalle)
                                                <tr>
                                                    <td class="text-center align-middle">{{ $detalle->sku }}</td>
                                                    <td class="text-center align-middle">
                                                        @if($detalle->tipo === 'creado')
                                                            <span class="badge bg-success">Creado</span>
                                                        @else
                                                            <span class="badge bg-warning text-dark">Actualizado</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-start">
                                                        @if($detalle->tipo === 'actualizado' && $detalle->datos_anteriores)
                                                            @php
                                                                $diferencias = array_diff_assoc($detalle->datos_nuevos, $detalle->datos_anteriores);
                                                            @endphp

                                                            @if (empty($diferencias))
                                                                <em class="text-muted">Sin diferencias detectadas.</em>
                                                            @else
                                                                @foreach ($diferencias as $campo => $valor)
                                                                    <strong>{{ ucfirst($campo) }}:</strong>
                                                                    <span class="text-danger">
                                                                        {{ $detalle->datos_anteriores[$campo] ?? '-' }}
                                                                    </span><br>
                                                                @endforeach
                                                            @endif
                                                        @else
                                                            <em class="text-muted">N/A</em>
                                                        @endif
                                                    </td>
                                                    <td class="text-start">
                                                        @if($detalle->tipo === 'actualizado' && $detalle->datos_anteriores)
                                                            @php
                                                                $diferencias = array_diff_assoc($detalle->datos_nuevos, $detalle->datos_anteriores);
                                                            @endphp

                                                            @if (empty($diferencias))
                                                                <em class="text-muted">Sin diferencias detectadas.</em>
                                                            @else
                                                                @foreach ($diferencias as $campo => $valor)
                                                                    <strong>{{ ucfirst($campo) }}:</strong>
                                                                    <span class="text-success">
                                                                        {{ $valor ?? '-' }}
                                                                    </span><br>
                                                                @endforeach
                                                            @endif
                                                        @else
                                                            {{-- Producto creado: mostrar todo --}}
                                                            @foreach ($detalle->datos_nuevos as $campo => $valor)
                                                                <strong>{{ ucfirst($campo) }}:</strong>
                                                                {{ $valor ?? '-' }}<br>
                                                            @endforeach
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $sincronizaciones->links() }}
        </div>
    </div>
@endsection