<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Categorías sincronizadas | {{ strtoupper($cliente) }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <style>
        body {
            background: #f6f8fb
        }

        .badge-dot {
            display: inline-flex;
            align-items: center;
            gap: .35rem
        }

        .badge-dot::before {
            content: "";
            width: .5rem;
            height: .5rem;
            border-radius: 50%
        }

        .badge-dot.success::before {
            background: #198754
        }

        .badge-dot.danger::before {
            background: #dc3545
        }

        .badge-dot.warning::before {
            background: #ffc107
        }

        .table thead th {
            white-space: nowrap
        }
    </style>
</head>

<body>
    <div class="container py-4">

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
            <h4 class="mb-2">
                <i class="fa-solid fa-folder-tree me-2"></i>
                Categorías sincronizadas — <span class="text-primary">{{ $cliente }}</span>
            </h4>
            <div class="text-muted small">
                <span class="me-3">Total: <b>{{ $totales['todas'] }}</b></span>
                <span class="me-3">Con productos: <b>{{ $totales['con'] }}</b></span>
                <span class="me-3">Sin productos: <b>{{ $totales['sin'] }}</b></span>
                <span>Eliminables: <b>{{ $totales['eliminables'] }}</b></span>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <form class="row g-2" method="get">
                    <div class="col-sm-3">
                        <label class="form-label small mb-1">Filtro</label>
                        <select name="filtro" class="form-select">
                            <option value="todas" {{ $filtro === 'todas' ? 'selected' : '' }}>Todas</option>
                            <option value="con" {{ $filtro === 'con' ? 'selected' : '' }}>Con productos</option>
                            <option value="sin" {{ $filtro === 'sin' ? 'selected' : '' }}>Sin productos</option>
                            <option value="eliminables" {{ $filtro === 'eliminables' ? 'selected' : '' }}>Sólo eliminables
                            </option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small mb-1">Buscar</label>
                        <input type="text" name="q" class="form-control" value="{{ $q }}"
                            placeholder="ID, nombre, slug o familia">
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small mb-1">Orden</label>
                        <div class="input-group">
                            <select name="orden" class="form-select">
                                <option value="name" {{ $orden === 'name' ? 'selected' : '' }}>Nombre</option>
                                <option value="woo_id" {{ $orden === 'woo_id' ? 'selected' : '' }}>ID Woo</option>
                                <option value="count" {{ $orden === 'count' ? 'selected' : '' }}>Count</option>
                            </select>
                            <select name="dir" class="form-select">
                                <option value="asc" {{ $dir === 'asc' ? 'selected' : '' }}>Asc</option>
                                <option value="desc" {{ $dir === 'desc' ? 'selected' : '' }}>Desc</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Aplicar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex gap-2 mb-2">
            <button id="btnEliminarSeleccion" class="btn btn-outline-danger">
                <i class="fa-solid fa-trash-can me-1"></i> Eliminar seleccionadas
            </button>
            <button id="btnEliminarTodasHuerfanas" class="btn btn-danger">
                <i class="fa-solid fa-broom me-1"></i> Eliminar TODAS huérfanas (eliminables)
            </button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tabla" class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:32px;">
                                    <input type="checkbox" id="chkAll">
                                </th>
                                <th>ID Woo</th>
                                <th>Nombre</th>
                                <th>Slug</th>
                                <th>Count</th>
                                <th>Familia SiReTT</th>
                                <th>Match</th>
                                <th>Eliminable</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $r)
                                <tr data-woo="{{ $r['woo_id'] }}">
                                    <td>
                                        <input type="checkbox" class="chkRow" {{ $r['count'] === 0 ? '' : 'disabled' }}>
                                    </td>
                                    <td><code>{{ $r['woo_id'] }}</code></td>
                                    <td>{{ $r['name'] }}</td>
                                    <td class="text-muted small">{{ $r['slug'] }}</td>
                                    <td>
                                        @if($r['count'] > 0)
                                            <span class="badge bg-success">{{ $r['count'] }}</span>
                                        @else
                                            <span class="badge bg-secondary">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($r['familia'])
                                            <span class="badge bg-info text-dark">{{ $r['familia'] }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $match = ($r['fam_key'] && $r['name_key'] && $r['fam_key'] === $r['name_key']) ? 'exact' : ($r['familia'] ? 'aprox' : 'none');
                                        @endphp
                                        @if($match === 'exact')
                                            <span class="badge badge-dot success"><span></span>exact</span>
                                        @elseif($match === 'aprox')
                                            <span class="badge badge-dot warning"><span></span>aprox</span>
                                        @else
                                            <span class="badge badge-dot danger"><span></span>none</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($r['eliminable'])
                                            <span class="badge bg-danger">Sí</span>
                                        @else
                                            <span class="badge bg-light text-dark">No</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-sm btn-outline-danger btnDeleteOne" {{ ($r['count'] === 0) ? '' : 'disabled' }}>
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                        @if($r['slug'])
                                            <a class="btn btn-sm btn-outline-secondary" target="_blank"
                                                href="https://wordpress.local/wp-admin/edit-tags.php?taxonomy=product_cat">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const RUTA_DELETE_ONE = "{{ route('catsync.deleteOne', ['cliente' => $cliente, 'wooId' => 999999]) }}".replace('999999', '');
        const RUTA_DELETE_SELECTED = "{{ route('catsync.deleteSelected', ['cliente' => $cliente]) }}";
        const RUTA_DELETE_ALL = "{{ route('catsync.deleteAllOrphans', ['cliente' => $cliente]) }}";
        const CSRF = "{{ csrf_token() }}";

        // DataTable
        const dt = new DataTable('#tabla', {
            pageLength: 25,
            order: [[2, 'asc']],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
            }
        });

        // Check all
        $('#chkAll').on('change', function () {
            const checked = this.checked;
            $('.chkRow').each(function () {
                if (!$(this).is(':disabled')) $(this).prop('checked', checked);
            });
        });

        // Eliminar una
        $(document).on('click', '.btnDeleteOne', async function () {
            const $tr = $(this).closest('tr');
            const id = $tr.data('woo');

            const ok = await Swal.fire({
                icon: 'warning',
                title: 'Eliminar categoría',
                html: '¿Seguro que deseas eliminar la categoría <b>ID ' + id + '</b>?<br><small>Debe estar sin productos y sin subcategorías.</small>',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            if (!ok.isConfirmed) return;

            const url = RUTA_DELETE_ONE + id;
            const res = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
            const j = await res.json();
            if (j.ok) {
                await Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1200, showConfirmButton: false });
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: j.msg || 'Error', footer: j.det || '' });
            }
        });

        // Eliminar seleccionadas
        $('#btnEliminarSeleccion').on('click', async function () {
            const ids = [];
            $('.chkRow:checked').each(function () {
                const id = $(this).closest('tr').data('woo');
                if (id) ids.push(id);
            });
            if (ids.length === 0) {
                return Swal.fire({ icon: 'info', title: 'Nada seleccionado', text: 'Marca al menos una categoría sin productos.' });
            }

            const ok = await Swal.fire({
                icon: 'warning',
                title: 'Eliminar seleccionadas',
                html: 'Se eliminarán <b>' + ids.length + '</b> categorías (deben estar sin productos).',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            if (!ok.isConfirmed) return;

            const res = await fetch(RUTA_DELETE_SELECTED, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ ids })
            });
            const j = await res.json();
            if (j.ok) {
                const okCount = (j.eliminadas || []).length;
                const errCount = (j.errores || []).length;
                await Swal.fire({ icon: 'success', title: 'Proceso finalizado', html: `Eliminadas: <b>${okCount}</b><br>Errores: <b>${errCount}</b>` });
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: j.msg || 'Ocurrió un problema' });
            }
        });

        // Eliminar TODAS huérfanas (eliminables)
        $('#btnEliminarTodasHuerfanas').on('click', async function () {
            const ok = await Swal.fire({
                icon: 'warning',
                title: 'Eliminar TODAS huérfanas',
                html: 'Se eliminarán todas las categorías marcadas como <b>eliminables</b> (sin productos y match exacto).',
                showCancelButton: true,
                confirmButtonText: 'Sí, proceder',
                cancelButtonText: 'Cancelar'
            });
            if (!ok.isConfirmed) return;

            const res = await fetch(RUTA_DELETE_ALL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF }
            });
            const j = await res.json();
            if (j.ok) {
                const okCount = (j.eliminadas || []).length;
                const errCount = (j.errores || []).length;
                await Swal.fire({ icon: 'success', title: 'Limpieza completa', html: `Eliminadas: <b>${okCount}</b><br>Errores: <b>${errCount}</b>` });
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: j.msg || 'Ocurrió un problema' });
            }
        });
    </script>
</body>

</html>