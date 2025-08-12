@extends('layouts.app')

@section('content')
<div class="container">
    <h4 class="mb-3 text-center fw-semibold">
        <i class="bi bi-clock-history"></i> Historial de Sincronizaciones
    </h4>

    {{-- Filtros por fecha --}}
    <form method="GET" action="{{ route('sync-history.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-3">
            <label for="desde" class="form-label mb-1">Fecha inicio</label>
            <input type="date" id="desde" name="desde" class="form-control form-control-sm"
                   value="{{ old('desde', request('desde', $desde ?? '')) }}">
        </div>
        <div class="col-12 col-md-3">
            <label for="hasta" class="form-label mb-1">Fecha fin</label>
            <input type="date" id="hasta" name="hasta" class="form-control form-control-sm"
                   value="{{ old('hasta', request('hasta', $hasta ?? '')) }}">
        </div>
        <div class="col-12 col-md-6 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm mt-3 mt-md-0">
                <i class="bi bi-funnel"></i> Filtrar
            </button>
            <a href="{{ route('sync-history.index') }}" class="btn btn-outline-secondary btn-sm mt-3 mt-md-0">
                <i class="bi bi-trash"></i> Limpiar
            </a>
            <a href="{{ route('sync-history.index') }}" class="btn btn-success btn-sm mt-3 mt-md-0">
                <i class="bi bi-arrow-clockwise"></i> Refrescar
            </a>
        </div>
    </form>

    <div class="table-responsive">
        
 <table class="table table-bordered table-hover table-sm align-middle text-center small">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Cliente</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Duración (s)</th>
            <th><i class="bi bi-plus-circle text-success" title="Creados"></i></th>
            <th><i class="bi bi-arrow-repeat text-primary" title="Actualizados"></i></th>
            <th><i class="bi bi-arrow-right-circle text-muted" title="Omitidos"></i></th>
            <th><i class="bi bi-exclamation-circle text-danger" title="Fallidos por categoría"></i></th>
            <th>Detalles</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sincronizaciones as $sync)
            <tr>
                <td>{{ $sync->id }}</td>
                <td>{{ $sync->cliente }}</td>

                {{-- Hora inicio (hh:mm AM/PM) --}}
                <td>
                    @if($sync->started_at)
                        @php 
                            $dt = \Carbon\Carbon::parse($sync->started_at);
                            $ampm = $dt->format('A');
                            $icono = $ampm === 'AM' ? 'bi-sun text-warning' : 'bi-moon text-primary';
                        @endphp
                        <span title="{{ $dt->format('d/m/Y h:i A') }}">
                            {{ $dt->format('h:i A') }}
                            <i class="bi {{ $icono }}"></i>
                        </span>
                    @else
                        —
                    @endif
                </td>

                {{-- Hora fin (hh:mm AM/PM) --}}
                <td>
                    @if($sync->finished_at)
                        @php 
                            $df = \Carbon\Carbon::parse($sync->finished_at);
                            $ampm = $df->format('A');
                            $icono = $ampm === 'AM' ? 'bi-sun text-warning' : 'bi-moon text-primary';
                        @endphp
                        <span title="{{ $df->format('d/m/Y h:i A') }}">
                            {{ $df->format('h:i A') }}
                            <i class="bi {{ $icono }}"></i>
                        </span>
                    @else
                        —
                    @endif
                </td>

                {{-- Duración en segundos con 2 decimales --}}
                <td>
                    @if($sync->started_at && $sync->finished_at)
                        @php
                            $segundos = \Carbon\Carbon::parse($sync->started_at)
                                        ->floatDiffInSeconds(\Carbon\Carbon::parse($sync->finished_at));
                        @endphp
                        {{ number_format($segundos, 2) }}
                    @else
                        —
                    @endif
                </td>

                <td class="text-success fw-bold">{{ $sync->total_creados }}</td>
                <td class="text-primary fw-bold">{{ $sync->total_actualizados }}</td>
                <td class="text-muted">{{ $sync->total_omitidos }}</td>
                <td class="text-danger">{{ $sync->total_fallidos_categoria }}</td>

                {{-- Columna de botones --}}
                <td class="text-nowrap" style="width:1%; white-space:nowrap;">
                    <div class="d-inline-flex align-items-center gap-1">
                        <button
                            class="btn btn-info btn-sm px-2 py-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modalDetalles-{{ $sync->id }}">
                            <i class="bi bi-eye"></i> Ver
                        </button>

                        <button
                            class="btn btn-danger btn-sm px-2 py-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modalStockCero-{{ $sync->id }}"
                            @if(!$sync->has_stock_cero_csv) disabled @endif>
                            <i class="bi bi-clipboard-check"></i> Stock=0
                        </button>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>



    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $sincronizaciones->withQueryString()->links() }}
    </div>
</div>

{{-- ===== Modales (render SSR, fuera de la tabla) ===== --}}
@foreach ($sincronizaciones as $sync)
    {{-- Modal Detalles --}}
    <div class="modal fade" id="modalDetalles-{{ $sync->id }}" tabindex="-1"
         aria-labelledby="labelDetalles-{{ $sync->id }}" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="labelDetalles-{{ $sync->id }}" class="modal-title">
                        Detalles de sincronización #{{ $sync->id }} — {{ $sync->cliente }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body" style="max-height:70vh; overflow:auto;">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered small">
                            <thead class="table-light text-center">
                                <tr>
                                    <th>SKU</th>
                                    <th>Tipo</th>
                                    <th style="width:45%">Antes</th>
                                    <th style="width:45%">Después</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sync->detalles as $detalle)
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
                                                @php $diferencias = array_diff_assoc($detalle->datos_nuevos, $detalle->datos_anteriores); @endphp
                                                @if (empty($diferencias))
                                                    <em class="text-muted">Sin diferencias detectadas.</em>
                                                @else
                                                    @foreach ($diferencias as $campo => $valor)
                                                        <strong>{{ ucfirst($campo) }}:</strong>
                                                        <span class="text-danger">{{ $detalle->datos_anteriores[$campo] ?? '-' }}</span><br>
                                                    @endforeach
                                                @endif
                                            @else
                                                <em class="text-muted">N/A</em>
                                            @endif
                                        </td>
                                        <td class="text-start">
                                            @if($detalle->tipo === 'actualizado' && $detalle->datos_anteriores)
                                                @php $diferencias = array_diff_assoc($detalle->datos_nuevos, $detalle->datos_anteriores); @endphp
                                                @if (empty($diferencias))
                                                    <em class="text-muted">Sin diferencias detectadas.</em>
                                                @else
                                                    @foreach ($diferencias as $campo => $valor)
                                                        <strong>{{ ucfirst($campo) }}:</strong>
                                                        <span class="text-success">{{ $valor ?? '-' }}</span><br>
                                                    @endforeach
                                                @endif
                                            @else
                                                @foreach ($detalle->datos_nuevos as $campo => $valor)
                                                    <strong>{{ ucfirst($campo) }}:</strong> {{ $valor ?? '-' }}<br>
                                                @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><em class="text-muted">Sin detalles.</em></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Stock=0 (lista copiable + descarga) --}}
    <div class="modal fade" id="modalStockCero-{{ $sync->id }}" tabindex="-1"
         aria-labelledby="labelStockCero-{{ $sync->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light">
                    <h6 id="labelStockCero-{{ $sync->id }}" class="modal-title fw-bold">
                        <span class="badge bg-primary">SKUs con stock = 0</span>
                        <span class="text-muted">en SiReTT — Sync #{{ $sync->id }}</span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    @if($sync->has_stock_cero_csv && count($sync->stock_cero_list) > 0)
                        <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <small class="text-muted">
                                Total: <strong>{{ count($sync->stock_cero_list) }}</strong> códigos
                            </small>

                            <div class="d-flex gap-2">
                                {{-- Descargar Excel/CSV --}}
                                <a href="{{ route('sync.stock_cero', $sync->id) }}"
                                   class="btn btn-success btn-sm {{ !$sync->has_stock_cero_csv ? 'disabled' : '' }}"
                                   @if(!$sync->has_stock_cero_csv) aria-disabled="true" tabindex="-1" @endif>
                                    <i class="bi bi-file-earmark-spreadsheet"></i> Descargar Excel
                                </a>

                                {{-- Copiar todo --}}
                                <button class="btn btn-outline-primary btn-sm"
                                        onclick="copyToClipboard('txtStockCero-{{ $sync->id }}')">
                                    <i class="bi bi-clipboard"></i> Copiar todo
                                </button>
                            </div>
                        </div>

                        {{-- Vista en columnas --}}
                        <div class="p-3 bg-dark text-white rounded small"
                             style="column-count: 4; column-gap: 2rem; font-family: monospace;">
                            @foreach($sync->stock_cero_list as $sku)
                                {{ $sku }}<br>
                            @endforeach
                        </div>

                        {{-- Área oculta para copy --}}
                        <textarea id="txtStockCero-{{ $sync->id }}" class="visually-hidden">@foreach($sync->stock_cero_list as $sku)
{{ $sku }}@endforeach</textarea>
                    @else
                        <em class="text-muted">No hay listado disponible para esta sincronización.</em>
                    @endif
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection

@push('scripts')
<script>
// Utilidad: copiar por id de textarea/elemento
function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;

    // Si es textarea, usamos su value; si no, su textContent
    const text = (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT') ? el.value : el.textContent;

    navigator.clipboard.writeText(text)
        .then(() => {
            // Opcional: pequeño feedback
            // alert('Copiado al portapapeles');
            console.log('Copiado al portapapeles');
        })
        .catch(() => {
            // Fallback (seleccionar y copiar)
            const temp = document.createElement('textarea');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            try { document.execCommand('copy'); } catch(e) {}
            document.body.removeChild(temp);
        });
}
</script>
@endpush
