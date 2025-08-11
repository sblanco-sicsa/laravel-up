@extends('layouts.app') {{-- Usa tu layout base --}}

@section('title', 'Categorías Sincronizadas - ' . $cliente)

@section('content')
    <div class="container py-4">
        <h2 class="mb-4">
            <i class="fas fa-tags"></i> Categorías Sincronizadas ({{ $cliente }})
        </h2>

        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>ID Woo</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categorias as $cat)
                                <tr>
                                    <td>{{ $loop->iteration + ($categorias->currentPage() - 1) * $categorias->perPage() }}</td>
                                    <td>{{ $cat->nombre }}</td>
                                    <td>{{ $cat->woocommerce_id ?? '-' }}</td>
                                    <td>{{ $cat->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <!-- Botón que abre el modal -->
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                                            data-bs-target="#jsonModal{{ $cat->id }}">
                                            <i class="fas fa-eye"></i> Ver JSON
                                        </button>

                                        <!-- Modal -->
                                        <div class="modal fade" id="jsonModal{{ $cat->id }}" tabindex="-1"
                                            aria-labelledby="jsonModalLabel{{ $cat->id }}" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title" id="jsonModalLabel{{ $cat->id }}">
                                                            JSON de categoría: {{ $cat->nombre }}
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                                            aria-label="Cerrar"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <pre class="bg-light p-3 rounded text-dark" style="font-size: 14px;">
                    {{ json_encode($cat->respuesta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                                                        </pre>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Cerrar</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No hay categorías sincronizadas aún.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>


        <div class="mt-3">
            {{ $categorias->links() }}
        </div>
    </div>
@endsection