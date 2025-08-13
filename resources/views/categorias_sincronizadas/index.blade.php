@extends('layouts.app')

@section('title', 'Categorías sincronizadas | ' . strtoupper($cliente))

@section('content')
    <div id="catsync-page" class="container py-4">
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

            <a href="{{ route('categorias.tree', ['cliente' => $cliente]) }}" class="btn btn-success" rel="noopener">
                <i class="fa-solid fa-sitemap me-1"></i> Ver árbol de categorías
            </a>


            <button id="btnEliminarSeleccion" class="btn btn-outline-danger">
                <i class="fa-solid fa-trash-can me-1"></i> Eliminar seleccionadas
            </button>
            <button id="btnEliminarTodasHuerfanas" class="btn btn-danger">
                <i class="fa-solid fa-broom me-1"></i> Eliminar TODAS huérfanas (eliminables)
            </button>
            <button id="btnSyncNow" class="btn btn-outline-primary">
                <i class="fa-solid fa-rotate me-1"></i> Sincronizar desde SiReTT
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
                                    {{-- <td class="text-nowrap">
                                        <button class="btn btn-sm btn-outline-danger btnDeleteOne" {{ ($r['count']===0) ? ''
                                            : 'disabled' }}>
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td> --}}
                                    <td class="text-nowrap">
                                        @if(($r['count'] === 0) && !empty($r['eliminable']))
                                            <form method="POST"
                                                action="{{ route('catsync.deleteOne', ['cliente' => $cliente, 'wooId' => $r['woo_id']]) }}"
                                                class="d-inline formDeleteOne">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        @else
                                            <button class="btn btn-sm btn-outline-secondary" disabled
                                                title="Solo categorías sin productos (eliminables)">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
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
@endsection

@push('styles')
    <style>
        /* Scopeamos estilos al contenedor de esta página para no alterar el layout */
        #catsync-page .badge-dot {
            display: inline-flex;
            align-items: center;
            gap: .35rem
        }

        #catsync-page .badge-dot::before {
            content: "";
            width: .5rem;
            height: .5rem;
            border-radius: 50%
        }

        #catsync-page .badge-dot.success::before {
            background: #198754
        }

        #catsync-page .badge-dot.danger::before {
            background: #dc3545
        }

        #catsync-page .badge-dot.warning::before {
            background: #ffc107
        }

        #catsync-page .table thead th {
            white-space: nowrap
        }
    </style>
@endpush

@push('scripts')
    <!-- Solo lo que NO está en el layout: SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Rutas & CSRF
        const RUTA_DELETE_ONE = "{{ route('catsync.deleteOne', ['cliente' => $cliente, 'wooId' => 999999]) }}".replace('999999', '');
        const RUTA_DELETE_SELECTED = "{{ route('catsync.deleteSelected', ['cliente' => $cliente]) }}";
        const RUTA_DELETE_ALL = "{{ route('catsync.deleteAllOrphans', ['cliente' => $cliente]) }}";
        const RUTA_SYNC_NOW = "{{ route('catsync.syncNow', ['cliente' => $cliente]) }}";
        const CSRF = "{{ csrf_token() }}";

        // Helper: determina si una fila puede borrarse (defensa en cliente)
        function filaEliminable($tr) {
            // 1) si el backend te pone data-eliminable="1" en <tr>, úsalo:
            const elAttr = $tr.data('eliminable'); // 1/0 si lo tienes disponible
            if (typeof elAttr !== 'undefined') {
                return String(elAttr) === '1';
            }

            // 2) fallback: leer el "count" que se muestra en la tabla (columna 5)
            //    Nota: es defensivo (UI), el servidor debe revalidar.
            const txt = $tr.find('td:nth-child(5) .badge').text().trim();
            const count = parseInt(txt, 10);
            if (!isNaN(count)) {
                return count === 0;
            }

            // 3) último recurso: usar el checkbox (si está deshabilitado, asumimos NO)
            const disabled = $tr.find('.chkRow').is(':disabled');
            return !disabled;
        }

        // DataTables v1.x (el layout carga 1.13.6)
        $(function () {
            const dt = $('#tabla').DataTable({
                pageLength: 25,
                order: [[2, 'asc']],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
            });

            // Check all
            $('#chkAll').on('change', function () {
                const checked = this.checked;
                $('.chkRow').each(function () {
                    if (!$(this).is(':disabled')) $(this).prop('checked', checked);
                });
            });

            // Eliminar UNA (con validación extra en cliente)
            $(document).on('click', '.btnDeleteOne', async function () {
                const $tr = $(this).closest('tr');
                const id = $tr.data('woo');

                // Defensa: no permitas si la fila no es eliminable (aunque quiten disabled)
                if (!filaEliminable($tr)) {
                    return Swal.fire({
                        icon: 'info',
                        title: 'No permitido',
                        text: 'Esta categoría no es eliminable (tiene productos o no cumple condiciones).'
                    });
                }

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

            // Eliminar SELECCIONADAS (filtra solo filas realmente eliminables)
            $('#btnEliminarSeleccion').on('click', async function () {
                const ids = [];
                $('.chkRow:checked').each(function () {
                    const $tr = $(this).closest('tr');
                    if (filaEliminable($tr)) {
                        const id = $tr.data('woo');
                        if (id) ids.push(id);
                    }
                });

                if (ids.length === 0) {
                    return Swal.fire({ icon: 'info', title: 'Nada seleccionable', text: 'No hay categorías eliminables marcadas.' });
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

            // Sincronizar ahora
            $('#btnSyncNow').on('click', async function () {
                const ok = await Swal.fire({
                    icon: 'question',
                    title: '¿Sincronizar categorías?',
                    html: 'Ejecutará la sincronización completa. Puede tardar unos segundos.',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, sincronizar',
                    cancelButtonText: 'Cancelar'
                });
                if (!ok.isConfirmed) return;

                Swal.fire({ title: 'Sincronizando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                try {
                    const res = await fetch(RUTA_SYNC_NOW, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
                    const j = await res.json();

                    if (!j.ok) {
                        return Swal.fire({ icon: 'error', title: 'Error', text: j.msg || 'Error', footer: j.det || '' });
                    }

                    const a = j.api || {};
                    await Swal.fire({
                        icon: 'success',
                        title: 'Sincronización completada',
                        html: `
                              <div class="text-start">
                                <b>Creadas:</b> ${a.creadas_total ?? 0}<br>
                                <b>Renombradas:</b> ${a.renombradas_total ?? 0}<br>
                                <b>Duplicadas eliminadas:</b> ${(a.duplicados?.eliminadas_total) ?? 0}
                              </div>`
                    });
                    location.reload();
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Fallo de red', text: e.message || 'Error' });
                }
            });
        });
    </script>
@endpush




{{-- @push('scripts')
<!-- Solo lo que NO está en el layout: SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Rutas & CSRF
    const RUTA_DELETE_ONE = "{{ route('catsync.deleteOne', ['cliente' => $cliente, 'wooId' => 999999]) }}".replace('999999', '');
    const RUTA_DELETE_SELECTED = "{{ route('catsync.deleteSelected', ['cliente' => $cliente]) }}";
    const RUTA_DELETE_ALL = "{{ route('catsync.deleteAllOrphans', ['cliente' => $cliente]) }}";
    const RUTA_SYNC_NOW = "{{ route('catsync.syncNow', ['cliente' => $cliente]) }}";
    const CSRF = "{{ csrf_token() }}";

    // DataTables v1.x (el layout carga 1.13.6)
    $(function () {
        const dt = $('#tabla').DataTable({
            pageLength: 25,
            order: [[2, 'asc']],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
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

            const res = await fetch(RUTA_DELETE_ALL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
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

        // Sincronizar ahora
        $('#btnSyncNow').on('click', async function () {
            const ok = await Swal.fire({
                icon: 'question',
                title: '¿Sincronizar categorías?',
                html: 'Ejecutará la sincronización completa. Puede tardar unos segundos.',
                showCancelButton: true,
                confirmButtonText: 'Sí, sincronizar',
                cancelButtonText: 'Cancelar'
            });
            if (!ok.isConfirmed) return;

            Swal.fire({ title: 'Sincronizando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            try {
                const res = await fetch(RUTA_SYNC_NOW, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
                const j = await res.json();

                if (!j.ok) {
                    return Swal.fire({ icon: 'error', title: 'Error', text: j.msg || 'Error', footer: j.det || '' });
                }

                const a = j.api || {};
                await Swal.fire({
                    icon: 'success',
                    title: 'Sincronización completada',
                    html: `
                          <div class="text-start">
                            <b>Creadas:</b> ${a.creadas_total ?? 0}<br>
                            <b>Renombradas:</b> ${a.renombradas_total ?? 0}<br>
                            <b>Duplicadas eliminadas:</b> ${(a.duplicados?.eliminadas_total) ?? 0}
                          </div>`
                });
                location.reload();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Fallo de red', text: e.message || 'Error' });
            }
        });
    });
</script>
@endpush --}}