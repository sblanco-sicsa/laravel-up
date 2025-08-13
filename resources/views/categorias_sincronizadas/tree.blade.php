@extends('layouts.app')

@section('title', 'Categorías (Árbol)')

@section('content')
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0">
      <i class="bi bi-diagram-3 me-2"></i>
      Jerarquía de categorías — <span class="text-uppercase">{{ $cliente }}</span>
    </h5>

    <div class="d-flex gap-2">
      @php $clienteActual = request()->route('cliente') ?? 'familyoutlet'; @endphp
      <a href="{{ route('catsync.index', ['cliente' => $clienteActual]) }}" class="btn btn-outline-secondary">
      <i class="bi bi-table me-1"></i> Volver a tabla
      </a>

      <button id="btnExpand" type="button" class="btn btn-outline-primary">
      <i class="bi bi-arrows-expand me-1"></i> Expandir todo
      </button>
      <button id="btnCollapse" type="button" class="btn btn-outline-primary">
      <i class="bi bi-arrows-collapse me-1"></i> Colapsar todo
      </button>
      <button id="btnReset" type="button" class="btn btn-outline-danger">
      <i class="bi bi-arrow-counterclockwise me-1"></i> Resetear a Woo
      </button>
      <button id="btnMakeMaster" type="button" class="btn btn-success">
      <i class="bi bi-arrow-bar-up me-1"></i> Hacer master
      </button>
      <button id="btnNewCat" type="button" class="btn btn-outline-success">
      <i class="bi bi-plus-circle me-1"></i> Nueva categoría
      </button>
      <button id="btnApplyWoo" type="button" class="btn btn-primary">
      <i class="bi bi-cloud-upload me-1"></i> Aplicar en Woo
      </button>
    </div>
    </div>

    <div class="card shadow-sm">
    <div class="card-body">
      <div class="tree-wrap"> {{-- <-- wrapper para aislar estilos --}} <div
        class="tree-header grid-cols mb-2 fw-semibold small text-muted px-2">
        <div class="col-name">Nombre</div>
        <div class="col-woo text-center">Woo ID</div>
        <div class="col-slug">Slug</div>
        <div class="col-actions text-center">Acciones</div>
      </div>

      <div id="catTree"></div>
    </div>

    <small class="text-muted d-block mt-3">
      Arrastra para cambiar padre. Usa ↥ en nodos hijos para volverlos <strong>MASTER</strong>.
    </small>
    </div>
  </div>



  </div>




  {{-- Modal nueva categoría --}}
  <div class="modal fade" id="modalNewCat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formNewCat">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nueva categoría</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
        <label class="form-label small">Nombre</label>
        <input type="text" class="form-control" name="nombre" required>
        </div>
        <div class="mb-2">
        <label class="form-label small">Slug (opcional)</label>
        <input type="text" class="form-control" name="slug" placeholder="se-generará-si-se-deja-vacío">
        </div>
        <div class="mb-2">
        <label class="form-label small">Padre</label>
        <select class="form-select" name="parent_id">
          <option value="">— MASTER (sin padre) —</option>
        </select>
        <div class="form-text">Si hay un nodo seleccionado, se propondrá como padre.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" type="submit">
        <i class="bi bi-check2 me-1"></i> Guardar
        </button>
      </div>
      </form>
    </div>
    </div>
  </div>
@endsection



@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jstree@3.3.15/dist/themes/default/style.min.css">
  <style>
    /* ======= Paleta y columnas (scoped a .tree-wrap) ======= */
    .tree-wrap {
    /* Fondo general suave */
    --tree-bg: #f6f8fb;
    --tree-card: #f9fbfd;
    --tree-border: #e6e9ef;
    --tree-hover: #f3f6fb;

    /* Tono base (azul) para filas por nivel */
    --row-hue: 221;
    --row-sat: 88%;
    --row-master: hsl(var(--row-hue) var(--row-sat) 96%);
    /* nivel 0 */
    --row-child-1: hsl(var(--row-hue) calc(var(--row-sat) - 15%) 97.5%);
    /* nivel 1 */
    --row-child-2: hsl(var(--row-hue) calc(var(--row-sat) - 25%) 98.5%);
    /* nivel 2 */
    --row-child-3: hsl(var(--row-hue) calc(var(--row-sat) - 35%) 99.2%);
    /* nivel 3+ */

    --indent: 16px;
    /* indent por nivel */
    --row-h: 40px;

    /* columnas desktop por defecto */
    --col-name: minmax(360px, 1fr);
    --col-woo: 110px;
    --col-slug: 280px;
    --col-actions: 200px;

    background: var(--tree-bg);
    padding: .25rem;
    border-radius: .5rem;
    }

    .tree-wrap .grid-cols {
    display: grid;
    grid-template-columns: var(--col-name) var(--col-woo) var(--col-slug) var(--col-actions);
    align-items: center;
    gap: .5rem;
    }

    .tree-wrap .tree-header {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--tree-card);
    border: 1px solid var(--tree-border);
    border-radius: .5rem;
    height: 36px;
    display: grid;
    align-items: center;
    }

    /* ======= Reseteos jsTree ======= */
    #catTree .jstree-container-ul,
    #catTree .jstree-children {
    margin: 0 !important;
    padding: 0 !important;
    background: none !important;
    }

    #catTree .jstree-node {
    margin-left: 0 !important;
    background: none !important;
    }

    #catTree .jstree-ocl,
    #catTree .jstree-themeicon {
    display: none !important;
    }

    /* ======= Fila tipo “tabla” con grid ======= */
    #catTree .jstree-anchor {
    width: 100%;
    min-height: var(--row-h);
    display: grid;
    grid-template-columns: var(--col-name) var(--col-woo) var(--col-slug) var(--col-actions);
    gap: .5rem;
    align-items: center;
    padding: .25rem .5rem;
    border: 1px solid var(--tree-border);
    border-radius: .5rem;
    background: #fff;
    /* se sobreescribe por nivel abajo */
    box-shadow: 0 1px 0 rgba(0, 0, 0, .02);
    }

    #catTree .jstree-anchor:hover {
    background: var(--tree-hover);
    }

    #catTree .jstree-anchor.jstree-clicked {
    background: rgba(13, 110, 253, .08);
    box-shadow: inset 0 0 0 1px rgba(13, 110, 253, .15);
    }

    /* Fondo por nivel (lo seteamos via data-depth en <li>) */
    #catTree li[data-depth="0"]>.jstree-anchor {
    background: var(--row-master);
    }

    #catTree li[data-depth="1"]>.jstree-anchor {
    background: var(--row-child-1);
    }

    #catTree li[data-depth="2"]>.jstree-anchor {
    background: var(--row-child-2);
    }

    #catTree li[data-depth="3"]>.jstree-anchor,
    #catTree li[data-depth="4"]>.jstree-anchor,
    #catTree li[data-depth="5"]>.jstree-anchor {
    background: var(--row-child-3);
    }

    /* celdas */
    #catTree .cell-name {
    display: flex;
    align-items: center;
    gap: .4rem;
    }

    /* indent visual según nivel (JS define --depth) */
    #catTree .cell-name {
    padding-left: calc(var(--depth, 0) * var(--indent));
    }

    #catTree .drag-handle {
    opacity: .55;
    cursor: grab;
    margin-right: .25rem;
    }

    #catTree .cell-woo {
    text-align: center;
    }

    #catTree .cell-slug {
    color: #6b7280;
    font-size: .86rem;
    }

    #catTree .cell-actions {
    display: flex;
    justify-content: center;
    gap: .25rem;
    }

    #catTree .cell-actions .btn {
    padding: .15rem .4rem;
    font-size: .70rem;
    }

    /* badge master */
    #catTree .badge-master {
    margin-left: .35rem;
    font-size: .65rem;
    font-weight: 600;
    background: rgba(13, 110, 253, .08);
    color: #0d6efd;
    border: 1px solid rgba(13, 110, 253, .25);
    padding: .1rem .35rem;
    border-radius: .25rem;
    }

    /* animación de “encontrado” al mover */
    #catTree .flash {
    animation: flashRow 1.2s ease-out 1;
    }

    @keyframes flashRow {
    0% {
      box-shadow: 0 0 0 0 rgba(25, 135, 84, .35);
    }

    50% {
      box-shadow: 0 0 0 .35rem rgba(25, 135, 84, .12);
    }

    100% {
      box-shadow: 0 0 0 0 rgba(25, 135, 84, .0);
    }
    }

    /* ======= Responsive ======= */
    /* md: ajusta anchos */
    @media (max-width: 992px) {
    .tree-wrap {
      --col-name: minmax(260px, 1fr);
      --col-slug: 220px;
      --col-actions: 170px;
      --indent: 14px;
    }
    }

    /* sm: solo mostramos Nombre + Acciones (Woo ID y Slug se ocultan) */
    @media (max-width: 768px) {
    .tree-wrap {
      --col-name: 1fr;
      --col-actions: 140px;
      --indent: 12px;
    }

    .tree-wrap .grid-cols {
      grid-template-columns: var(--col-name) var(--col-actions);
    }

    .tree-wrap .grid-cols .col-woo,
    .tree-wrap .grid-cols .col-slug {
      display: none;
    }

    #catTree .jstree-anchor {
      grid-template-columns: var(--col-name) var(--col-actions);
    }

    #catTree .cell-woo,
    #catTree .cell-slug {
      display: none;
    }

    #catTree .cell-actions .btn {
      padding: .2rem .45rem;
      font-size: .75rem;
    }
    }
  </style>
@endpush




@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/jstree@3.3.15/dist/jstree.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
    // === RUTAS ===
    const RUTA_TREE = @json(route('categorias.api.tree', ['cliente' => $cliente]));
    const RUTA_MOVE = @json(route('categorias.api.move', ['cliente' => $cliente]));
    const RUTA_RESET = @json(route('categorias.api.reset', ['cliente' => $cliente]));
    const RUTA_APPLY = @json(route('woo.categories.applyHierarchy', ['cliente' => $cliente]));
    const RUTA_STORE = @json(route('categorias.api.store', ['cliente' => $cliente]));

    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF } });

    const $treeEl = $('#catTree');

    const swalLoading = (title = 'Procesando…') =>
      Swal.fire({ title, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const toast = (icon, title) =>
      Swal.fire({ toast: true, icon, title, position: 'top-end', timer: 1800, showConfirmButton: false });

    // ——— Decorado tipo tabla ———


    // Pinta la estructura de celdas
    function decorateRow(inst, nodeId) {
      const node = inst.get_node(nodeId);
      const $li = inst.get_node(nodeId, true);
      if (!$li.length) return;
      const $a = $li.children('.jstree-anchor');

      if ($a.find('.cell-name').length) return; // ya decorado

      const rawName = inst.get_text(nodeId);
      const wid = ($li.data('wid') ?? '—');
      const slug = ($li.data('slug') ?? '—');

      $a.empty();

      const $name = $('<div class="cell-name"></div>');
      $name.append('<span class="drag-handle" title="Arrastrar"><i class="bi bi-grip-vertical"></i></span>');
      $name.append('<i class="bi bi-folder2"></i>');
      $name.append('<span class="text-truncate">' + rawName + '</span>');
      $a.append($name);

      $a.append('<div class="cell-woo">' + wid + '</div>');
      $a.append('<div class="cell-slug">' + slug + '</div>');
      $a.append('<div class="cell-actions"></div>');

      // → indent por profundidad (node.parents incluye '#')
      const depth = Math.max(0, (node.parents?.length || 1) - 1);
      $name[0].style.setProperty('--depth', depth);

      // Fondo por nivel: lo usamos en CSS
      $li.attr('data-depth', depth);
    }

    function renderNodeTools(inst, nodeId) {
      decorateRow(inst, nodeId);

      const node = inst.get_node(nodeId);
      const $li = inst.get_node(nodeId, true);
      if (!$li.length) return;
      const $a = $li.children('.jstree-anchor');

      $a.find('.badge-master').remove();
      const $actions = $a.find('.cell-actions').empty();

      const isMaster = (node.parent === '#');
      const hasChildren = inst.is_parent(node);

      if (isMaster) {
      $a.find('.cell-name').append('<span class="badge-master">MASTER</span>');
      if (hasChildren) {
        $actions.append('<button class="btn btn-outline-secondary btn-xs btn-toggle-node" title="Expandir/contraer"><i class="bi bi-caret-down-fill"></i></button>');
      }
      return;
      }

      $actions.append('<button class="btn btn-outline-success btn-xs btn-make-master" title="Convertir en MASTER"><i class="bi bi-arrow-bar-up"></i></button>');
      if (hasChildren) {
      $actions.append('<button class="btn btn-outline-secondary btn-xs btn-toggle-node" title="Expandir/contraer"><i class="bi bi-caret-down-fill"></i></button>');
      }
    }

    function refreshAllTools(inst) {
      inst.get_json('#', { flat: true }).forEach(n => {
      decorateRow(inst, n.id);
      renderNodeTools(inst, n.id);
      });
    }




    // ——— jsTree ———
    $treeEl
      .jstree({
      core: {
        check_callback: true,
        multiple: false,
        themes: { stripes: false },
        animation: 120,
        data: function (_node, cb) {
        $.getJSON(RUTA_TREE)
          .done(resp => Array.isArray(resp) ? cb(resp) : (console.error('TREE no array', resp), cb([])))
          .fail(xhr => { console.error('TREE fail', xhr.status, xhr.responseText?.slice(0, 200)); cb([]); });
        }
      },
      dnd: { large_drag_target: true, open_timeout: 150 },
      plugins: ['dnd', 'state', 'types', 'unique', 'sort'],
      sort: function (a, b) { return this.get_text(a).localeCompare(this.get_text(b), 'es', { sensitivity: 'base', numeric: true }); },
      types: {
        master: { icon: 'bi bi-folder-fill text-primary' },
        child: { icon: 'bi bi-folder2' },
        default: { icon: 'bi bi-folder' }
      }
      })

      .on('ready.jstree refresh.jstree redraw.jstree', (e, d) => refreshAllTools(d.instance))
      .on('after_open.jstree after_close.jstree', (e, d) => { if (d?.node?.id) renderNodeTools(d.instance, d.node.id); })
      .on('changed.jstree', (e, d) => {
      const inst = d.instance;
      if (d?.node?.id) {
        renderNodeTools(inst, d.node.id);
        if (d.node.parent && d.node.parent !== '#') renderNodeTools(inst, d.node.parent);
      }
      })
      .on('move_node.jstree', function (e, data) {
      const inst = data.instance;
      const nuevoParent = (data.parent === '#') ? null : data.parent;

      $.post(RUTA_MOVE, { id: data.node.id, parent: nuevoParent, position: data.position })
        .done(() => {
        if (nuevoParent) inst.open_node(nuevoParent);
        inst.set_type(data.node, nuevoParent ? 'child' : 'master');
        renderNodeTools(inst, data.node.id);
        if (nuevoParent) renderNodeTools(inst, nuevoParent);

        inst.deselect_all(); inst.select_node(data.node.id);
        const $row = inst.get_node(data.node, true);
        $row.addClass('flash'); setTimeout(() => $row.removeClass('flash'), 1200);
        $row[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });

        toast('success', 'Jerarquía actualizada');
        })
        .fail(xhr => { Swal.fire({ icon: 'error', title: 'No se pudo mover', text: xhr.responseJSON?.msg ?? xhr.responseJSON?.error ?? 'Error' }); inst.refresh(); });
      });

    // ——— Acciones globales ———
    $('#btnExpand').on('click', () => $treeEl.jstree('open_all'));
    $('#btnCollapse').on('click', () => $treeEl.jstree('close_all'));

    // Toggle fila (delegado)
    $(document).on('click', '.btn-toggle-node', function (ev) {
      ev.preventDefault();
      const inst = $treeEl.jstree(true);
      const id = $(this).closest('.jstree-node').attr('id'); if (!id) return;
      inst.is_open(id) ? inst.close_node(id) : inst.open_node(id);
    });

    // Hacer MASTER seleccionado
    $('#btnMakeMaster').on('click', () => {
      const inst = $treeEl.jstree(true);
      const sel = inst.get_selected(true);
      if (sel.length !== 1) return Swal.fire({ icon: 'info', title: 'Selecciona exactamente una categoría.' });
      const node = sel[0]; if (node.parent === '#') return toast('info', 'Ya es master');
      inst.move_node(node, '#', 'last');
    });

    // Hacer MASTER por fila
    $(document).on('click', '.btn-make-master', function (ev) {
      ev.preventDefault();
      const inst = $treeEl.jstree(true);
      const id = $(this).closest('.jstree-node').attr('id'); if (!id) return;
      const node = inst.get_node(id); if (node.parent === '#') return;
      inst.move_node(node, '#', 'last');
    });

    // Reset
    $('#btnReset').on('click', async () => {
      const ok = await Swal.fire({ icon: 'question', title: 'Resetear jerarquía', html: 'Volverá a la estructura de Woo.', showCancelButton: true, confirmButtonText: 'Sí, resetear', cancelButtonText: 'Cancelar' });
      if (!ok.isConfirmed) return;
      swalLoading('Reseteando…');
      $.post(RUTA_RESET)
      .done(() => { Swal.close(); $treeEl.jstree(true).refresh(); toast('success', 'Jerarquía reseteada'); })
      .fail(xhr => { Swal.close(); Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.msg ?? 'No se pudo resetear' }); });
    });

    // Aplicar en Woo
    $('#btnApplyWoo').on('click', async (e) => {
      e.preventDefault();
      const ok = await Swal.fire({ icon: 'question', title: 'Aplicar en WooCommerce', html: 'Creará/actualizará categorías según esta jerarquía.', showCancelButton: true, confirmButtonText: 'Sí, aplicar', cancelButtonText: 'Cancelar' });
      if (!ok.isConfirmed) return;
      swalLoading('Aplicando en Woo…');
      $.post(RUTA_APPLY)
      .done(resp => { Swal.close(); toast('success', resp?.msg ?? 'Listo'); })
      .fail(xhr => { Swal.close(); Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.error ?? xhr.responseJSON?.message ?? 'Fallo al aplicar' }); });
    });

    // ——— Nueva categoría (modal) ———
    const newCatModal = new bootstrap.Modal(document.getElementById('modalNewCat'));
    $('#btnNewCat').on('click', () => {
      // Prefill: padre = seleccionado
      const inst = $treeEl.jstree(true);
      const sel = inst.get_selected(true);
      const $sel = $('select[name="parent_id"]').empty().append('<option value="">— MASTER (sin padre) —</option>');
      // Cargar opciones rápidas (simple: todos los nodos visibles)
      inst.get_json('#', { flat: true }).forEach(n => {
      $sel.append(`<option value="${n.id}">${inst.get_text(n.id)}</option>`);
      });
      if (sel.length === 1) $sel.val(sel[0].id);

      $('#formNewCat')[0].reset();
      newCatModal.show();
    });

    $('#formNewCat').on('submit', function (e) {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(this).entries());
      swalLoading('Creando categoría…');
      $.post(RUTA_STORE, payload)
      .done(() => { Swal.close(); newCatModal.hide(); $treeEl.jstree(true).refresh(); toast('success', 'Categoría creada'); })
      .fail(xhr => { Swal.close(); Swal.fire({ icon: 'error', title: 'No se pudo crear', text: xhr.responseJSON?.message ?? 'Error' }); });
    });
    });
  </script>
@endpush