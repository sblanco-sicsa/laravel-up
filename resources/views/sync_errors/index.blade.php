@extends('layouts.app')
@section('title', 'Errores de Sincronización')

@section('content')
    <h4 class="mb-4 text-center text-danger">
        <i class="bi bi-exclamation-circle"></i> Errores de Sincronización
    </h4>

    @if(session('success'))
        <div class="alert alert-success text-center">{{ session('success') }}</div>
    @endif

    <div class="mb-3 text-end">
        <a href="{{ route('sync-errors.export') }}" class="btn btn-success me-2">
            <i class="fas fa-file-excel"></i> Exportar a Excel
        </a>

        <form action="{{ route('sync-errors.delete-all') }}" method="POST" class="d-inline"
            onsubmit="return confirm('¿Estás seguro de eliminar todos los errores?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash-alt"></i> Eliminar Todos
            </button>
        </form>
    </div>

    @if($errores->count())
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center small" id="tabla-sync">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Tipo de Error</th>
                        <th>Detalle</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($errores as $error)
                                    <tr>
                                        <td>{{ $error->id }}</td>
                                        <td>{{ $error->sku ?? '—' }}</td>
                                        <td><span class="badge bg-danger">{{ $error->tipo_error }}</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                data-bs-target="#detalleModal{{ $error->id }}">
                                                Ver
                                            </button>

                                            <!-- Modal -->
                                            <div class="modal fade" id="detalleModal{{ $error->id }}" tabindex="-1"
                                                aria-labelledby="modalLabel{{ $error->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="modalLabel{{ $error->id }}">
                                                                Detalle del Error (SKU: {{ $error->sku }})
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <pre class="bg-light p-3 rounded small">
                        {{ json_encode(json_decode($error->detalle, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                                                                    </pre>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $error->created_at->format('Y-m-d H:i:s') }}</td>
                                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $errores->links('pagination::bootstrap-5') }}
    @else
        <div class="alert alert-info text-center">No hay errores registrados.</div>
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            $('#tabla-sync').DataTable({
                responsive: true,
                language: {
                    paginate: {
                        previous: "Anterior",
                        next: "Siguiente"
                    },
                    emptyTable: "No hay datos disponibles",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    search: "Buscar:"
                },
                paging: false,
                searching: false,
                info: false,
                ordering: false
            });
        });
    </script>
@endpush