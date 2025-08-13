@extends('layouts.app')

@section('title', 'Categorías (Árbol)')

@section('content')
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="mb-0">
        <i class="bi bi-diagram-3 me-2"></i>
        Jerarquía de categorías — <span class="text-uppercase">{{ $cliente }}</span>
      </h5>

      <div class="d-flex gap-2 tree-toolbar">
        @php $clienteActual = request()->route('cliente') ?? 'familyoutlet'; @endphp
        <a href="{{ route('catsync.index', ['cliente' => $clienteActual]) }}" class="btn btn-outline-secondary">
          <i class="bi bi-table me-1"></i> Volver a tabla
        </a>

        <button id="btnExpand"   type="button" class="btn btn-outline-primary">
          <i class="bi bi-arrows-expand me-1"></i> Expandir todo
        </button>
        <button id="btnCollapse" type="button" class="btn btn-outline-primary">
          <i class="bi bi-arrows-collapse me-1"></i> Colapsar todo
        </button>
        <button id="btnReset"    type="button" class="btn btn-outline-danger">
          <i class="bi bi-arrow-counterclockwise me-1"></i> Resetear a Woo
        </button>
        <button id="btnMakeMaster" type="button" class="btn btn-success">
          <i class="bi bi-arrow-bar-up me-1"></i> Hacer master
        </button>
        <button id="btnNewCat"  type="button" class="btn btn-outline-success">
          <i class="bi bi-plus-circle me-1"></i> Nueva categoría
        </button>
        <button id="btnApplyWoo"  type="button" class="btn btn-primary">
          <i class="bi bi-cloud-upload me-1"></i> Aplicar en Woo
        </button>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="tree-wrap">
          <div class="tree-header grid-cols mb-2 fw-semibold small text-muted px-2">
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
  .tree-wrap{
    --tree-bg:        #f6f8fb;
    --tree-card:      #f9fbfd;
    --tree-border:    #e6e9ef;
    --tree-hover:     #f3f6fb;

    /* Tono base (azul) por nivel */
    --row-hue: 221;
    --row-sat: 88%;
    --row-master:  hsl(var(--row-hue) var(--row-sat) 96%);            /* nivel 0 */
    --row-child-1: hsl(var(--row-hue) calc(var(--row-sat)-15%) 97.5%);/* 1 */
    --row-child-2: hsl(var(--row-hue) calc(var(--row-sat)-25%) 98.5%);/* 2 */
    --row-child-3: hsl(var(--row-hue) calc(var(--row-sat)-35%) 99.2%);/* 3+ */

    --indent:   16px;
    --row-h:    40px;

    --col-name:    minmax(360px, 1fr);
    --col-woo:     110px;
    --col-slug:    280px;
    --col-actions: 200px;

    /* Colores de línea tipo “tabla” */
    --line:        #c0c7de;  /* bordes generales */
    --line-strong: #9aa7c7;  /* separadores internos */

    position: relative;
    background: var(--tree-bg);
    padding: .25rem;
    border-radius: .5rem;
    overflow-x: hidden; /* evita desbordes laterales */
  }

  /* toolbar responsive */
  .tree-toolbar{ flex-wrap: wrap; gap: .5rem; }

  .tree-wrap .grid-cols{
    display: grid;
    grid-template-columns: var(--col-name) var(--col-woo) minmax(0, var(--col-slug)) var(--col-actions);
    align-items: center;
    gap: .5rem;
  }

  .tree-wrap .tree-header{
    position: sticky; top: 0; z-index: 2;
    background: var(--tree-card);
    border: 2px solid var(--line);
    border-radius: .5rem;
    height: 36px;
    display: grid;
    align-items: center;
    box-shadow: inset 0 -2px 0 var(--line-strong);
    padding: .25rem .5rem;              /* mismo padding que las filas */
    box-sizing: border-box;
  }

  /* ======= Reset jsTree ======= */
  #catTree .jstree-container-ul,
  #catTree .jstree-children{ margin:0!important; padding:0!important; background:none!important; }
  #catTree .jstree-node{ margin-left:0!important; background:none!important; }
  #catTree .jstree-ocl,
  #catTree .jstree-themeicon{ display:none!important; }

  /* ======= Filas tipo tabla ======= */
  #catTree .jstree-anchor{
    position: relative;
    z-index: 0;
    width: 100%;
    min-height: var(--row-h);
    display: grid;
    grid-template-columns: var(--col-name) var(--col-woo) minmax(0, var(--col-slug)) var(--col-actions);
    gap: .5rem;
    align-items: center;

    padding: .25rem .5rem;
    padding-left: calc(.5rem + (var(--depth, 0) * var(--indent)));

    background: #fff;                 /* fallback */
    border: 2px solid transparent;    /* para que el alto no cambie al hover */
    border-radius: .5rem;
    box-sizing: border-box;
  }
  #catTree .jstree-anchor::before{
    content:'';
    position:absolute;
    top:0; bottom:0; right:0;         /* usamos right:0 para evitar desbordes */
    left: calc(var(--depth, 0) * var(--indent));
    border: 2px solid var(--line);
    box-shadow: inset 0 -2px 0 var(--line-strong);
    border-radius: .5rem;
    background: var(--row-master);
    z-index: 0;
    box-sizing: border-box;           /* bordes incluidos en el tamaño */
  }
  /* contenido siempre por encima del ::before */
  #catTree .jstree-anchor > *{
    position: relative; z-index: 1; min-width: 0;
  }

  #catTree .jstree-anchor:hover::before{
    background: var(--tree-hover);
    border-color: var(--line-strong);
    box-shadow: inset 0 -2px 0 var(--line-strong);
  }
  #catTree .jstree-anchor.jstree-clicked::before{
    background: rgba(13,110,253,.08);
    box-shadow: inset 0 0 0 2px rgba(13,110,253,.15);
  }

  /* Fondo por nivel (data-depth en <li>) */
  #catTree li[data-depth="0"] > .jstree-anchor::before{ background: var(--row-master); }
  #catTree li[data-depth="1"] > .jstree-anchor::before{ background: var(--row-child-1); }
  #catTree li[data-depth="2"] > .jstree-anchor::before{ background: var(--row-child-2); }
  #catTree li[data-depth="3"] > .jstree-anchor::before,
  #catTree li[data-depth="4"] > .jstree-anchor::before,
  #catTree li[data-depth="5"] > .jstree-anchor::before{ background: var(--row-child-3); }

  /* Celdas */
  #catTree .cell-name{ display:flex; align-items:center; gap:.4rem; min-width:0; }
  #catTree .drag-handle{ opacity:.6; cursor:grab; margin-right:.25rem; }
  #catTree .cell-woo   { text-align:center; white-space:nowrap; }
  #catTree .cell-slug  {
    color:#6b7280; font-size:.86rem;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap; /* que no empuje la grilla */
  }
  #catTree .cell-actions{ display:flex; justify-content:center; gap:.25rem; }
  #catTree .cell-actions .btn{ padding:.15rem .45rem; font-size:.75rem; }

  /* Divisores verticales entre columnas */
  #catTree .cell-woo,
  #catTree .cell-slug,
  #catTree .cell-actions{
    border-left: 1px solid var(--line-strong);
    padding-left: .65rem;
  }
  /* Header con divisores */
  .tree-wrap .tree-header > .col-woo,
  .tree-wrap .tree-header > .col-slug,
  .tree-wrap .tree-header > .col-actions{
    border-left: 1px solid var(--line-strong);
    padding-left: .65rem;
  }

  /* separación sutil entre bloques master */
  #catTree li[data-depth="0"]{ margin-top: .35rem !important; }

  /* Badge MASTER */
  #catTree .badge-master{
    margin-left:.35rem; font-size:.65rem; font-weight:600;
    background:rgba(13,110,253,.08); color:#0d6efd; border:1px solid rgba(13,110,253,.25);
    padding:.1rem .35rem; border-radius:.25rem;
  }

  /* Animación de realce */
  #catTree .flash{ animation:flashRow 1.2s ease-out 1; }
  @keyframes flashRow{
    0%{ box-shadow:0 0 0 0 rgba(25,135,84,.35); }
    50%{ box-shadow:0 0 0 .35rem rgba(25,135,84,.12); }
    100%{ box-shadow:0 0 0 0 rgba(25,135,84,.0); }
  }

  /* ======= Responsive ======= */
  @media (max-width: 1200px){
    .tree-wrap{ --col-slug: 240px; --col-actions: 180px; }
  }
  @media (max-width: 992px){
    .tree-wrap{
      --col-name:    minmax(260px, 1fr);
      --col-slug:    220px;
      --col-actions: 170px;
      --indent:      14px;
    }
  }
  @media (max-width: 768px){
    .tree-wrap{
      --col-name:    1fr;
      --col-actions: 140px;
      --indent:      12px;
    }
    .tree-wrap .grid-cols{ grid-template-columns: var(--col-name) var(--col-actions); }
    .tree-wrap .grid-cols .col-woo,
    .tree-wrap .grid-cols .col-slug{ display:none; }

    #catTree .jstree-anchor{
      grid-template-columns: var(--col-name) var(--col-actions);
    }
    #catTree .cell-woo, #catTree .cell-slug{ display:none; }
    #catTree .cell-actions .btn{ padding:.2rem .5rem; font-size:.78rem; }
  }
  @media (max-width: 576px){
    .tree-wrap{ --col-actions: 120px; }
    .tree-toolbar .btn{ width: 100%; } /* botones a una columna en móviles muy pequeños */
  }
</style>
@endpush


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jstree@3.3.15/dist/jstree.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // === RUTAS ===
  const RUTA_TREE  = @json(route('categorias.api.tree',  ['cliente' => $cliente]));
  const RUTA_MOVE  = @json(route('categorias.api.move',  ['cliente' => $cliente]));
  const RUTA_RESET = @json(route('categorias.api.reset', ['cliente' => $cliente]));
  const RUTA_APPLY = @json(route('woo.categories.applyHierarchy', ['cliente' => $cliente]));
  const RUTA_STORE = @json(route('categorias.api.store', ['cliente' => $cliente]));

  const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF } });

  const $treeEl = $('#catTree');

  const swalLoading = (title='Procesando…') =>
    Swal.fire({ title, allowOutsideClick:false, didOpen:() => Swal.showLoading() });
  const toast = (icon, title) =>
    Swal.fire({ toast:true, icon, title, position:'top-end', timer:1800, showConfirmButton:false });

  // ——— Decorado tipo tabla ———
  function decorateRow(inst, nodeId){
    const node = inst.get_node(nodeId);
    const $li  = inst.get_node(nodeId, true);
    if (!$li.length) return;

    const $a   = $li.children('.jstree-anchor');

    // profundidad (actualiza SIEMPRE)
    const depth = Math.max(0, (node.parents?.length || 1) - 1);
    $li.attr('data-depth', depth);
    if ($a[0]) $a[0].style.setProperty('--depth', depth);

    // si ya está armado, no re-crear HTML
    if ($a.find('.cell-name').length) return;

    const rawName = inst.get_text(nodeId);
    const wid  = ($li.data('wid')  ?? '—');
    const slug = ($li.data('slug') ?? '—');

    $a.empty();

    const $name = $('<div class="cell-name"></div>');
    $name.append('<span class="drag-handle" title="Arrastrar"><i class="bi bi-grip-vertical"></i></span>');
    $name.append('<i class="bi bi-folder2"></i>');
    $name.append('<span class="text-truncate">'+ rawName +'</span>');
    $a.append($name);

    $a.append('<div class="cell-woo">'+ wid +'</div>');
    $a.append('<div class="cell-slug">'+ slug +'</div>');
    $a.append('<div class="cell-actions"></div>');
  }

  function renderNodeTools(inst, nodeId){
    decorateRow(inst, nodeId);

    const node = inst.get_node(nodeId);
    const $li  = inst.get_node(nodeId, true);
    if (!$li.length) return;
    const $a   = $li.children('.jstree-anchor');

    $a.find('.badge-master').remove();
    const $actions = $a.find('.cell-actions').empty();

    const isMaster    = (node.parent === '#');
    const hasChildren = inst.is_parent(node);

    if (isMaster){
      $a.find('.cell-name').append('<span class="badge-master">MASTER</span>');
      if (hasChildren){
        $actions.append('<button class="btn btn-outline-secondary btn-xs btn-toggle-node" title="Expandir/contraer"><i class="bi bi-caret-down-fill"></i></button>');
      }
      return;
    }

    $actions.append('<button class="btn btn-outline-success btn-xs btn-make-master" title="Convertir en MASTER"><i class="bi bi-arrow-bar-up"></i></button>');
    if (hasChildren){
      $actions.append('<button class="btn btn-outline-secondary btn-xs btn-toggle-node" title="Expandir/contraer"><i class="bi bi-caret-down-fill"></i></button>');
    }
  }

  function refreshAllTools(inst){
    inst.get_json('#', { flat:true }).forEach(n => {
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
        data: function(_node, cb){
          $.getJSON(RUTA_TREE)
            .done(resp => Array.isArray(resp) ? cb(resp) : (console.error('TREE no array', resp), cb([])))
            .fail(xhr  => { console.error('TREE fail', xhr.status, xhr.responseText?.slice(0,200)); cb([]); });
        }
      },
      dnd: { large_drag_target:true, open_timeout:150 },
      plugins: ['dnd','state','types','unique','sort'],
      sort: function(a,b){ return this.get_text(a).localeCompare(this.get_text(b),'es',{sensitivity:'base',numeric:true}); },
      types: { master:{icon:'bi bi-folder-fill text-primary'}, child:{icon:'bi bi-folder2'}, default:{icon:'bi bi-folder'} }
    })

    .on('ready.jstree refresh.jstree redraw.jstree', (e,d) => refreshAllTools(d.instance))
    .on('open_node.jstree', (e,d) => {
      const inst = d.instance;
      renderNodeTools(inst, d.node.id);
      inst.get_children_dom(d.node).each(function(){ const id=this.id; if(id) renderNodeTools(inst, id); });
    })
    .on('close_node.jstree', (e,d) => { renderNodeTools(d.instance, d.node.id); })
    .on('changed.jstree', (e,d) => {
      const inst = d.instance;
      if (d?.node?.id){
        renderNodeTools(inst, d.node.id);
        if (d.node.parent && d.node.parent !== '#') renderNodeTools(inst, d.node.parent);
      }
    })
    .on('move_node.jstree', function(e, data){
      const inst = data.instance;
      const nuevoParent = (data.parent === '#') ? null : data.parent;

      $.post(RUTA_MOVE, { id:data.node.id, parent:nuevoParent, position:data.position })
        .done(() => {
          if (nuevoParent) inst.open_node(nuevoParent);
          inst.set_type(data.node, nuevoParent ? 'child' : 'master');
          renderNodeTools(inst, data.node.id);
          if (nuevoParent) renderNodeTools(inst, nuevoParent);

          inst.deselect_all(); inst.select_node(data.node.id);
          const $row = inst.get_node(data.node, true);
          $row.addClass('flash'); setTimeout(() => $row.removeClass('flash'), 1200);
          $row[0]?.scrollIntoView({ behavior:'smooth', block:'center' });

          toast('success','Jerarquía actualizada');
        })
        .fail(xhr => { Swal.fire({icon:'error', title:'No se pudo mover', text:xhr.responseJSON?.msg ?? xhr.responseJSON?.error ?? 'Error'}); inst.refresh(); });
    });

  // ——— Acciones globales ———
  $('#btnExpand').on('click', () => {
    const inst = $treeEl.jstree(true);
    inst.open_all();
    setTimeout(() => refreshAllTools(inst), 0);
  });

  $('#btnCollapse').on('click', () => {
    const inst = $treeEl.jstree(true);
    inst.close_all();
    setTimeout(() => refreshAllTools(inst), 0);
  });

  $(document).on('click', '.btn-toggle-node', function (ev) {
    ev.preventDefault();
    const inst = $treeEl.jstree(true);
    const id   = $(this).closest('.jstree-node').attr('id'); if (!id) return;
    inst.is_open(id) ? inst.close_node(id) : inst.open_node(id);
  });

  $('#btnMakeMaster').on('click', () => {
    const inst = $treeEl.jstree(true);
    const sel  = inst.get_selected(true);
    if (sel.length !== 1) return Swal.fire({icon:'info',title:'Selecciona exactamente una categoría.'});
    const node = sel[0]; if (node.parent === '#') return toast('info','Ya es master');
    inst.move_node(node, '#', 'last');
  });

  $(document).on('click', '.btn-make-master', function (ev) {
    ev.preventDefault();
    const inst = $treeEl.jstree(true);
    const id   = $(this).closest('.jstree-node').attr('id'); if (!id) return;
    const node = inst.get_node(id); if (node.parent === '#') return;
    inst.move_node(node, '#', 'last');
  });

  $('#btnReset').on('click', async () => {
    const ok = await Swal.fire({icon:'question', title:'Resetear jerarquía', html:'Volverá a la estructura de Woo.', showCancelButton:true, confirmButtonText:'Sí, resetear', cancelButtonText:'Cancelar'});
    if (!ok.isConfirmed) return;
    swalLoading('Reseteando…');
    $.post(RUTA_RESET)
      .done(() => { Swal.close(); $treeEl.jstree(true).refresh(); toast('success','Jerarquía reseteada'); })
      .fail(xhr => { Swal.close(); Swal.fire({icon:'error',title:'Error',text:xhr.responseJSON?.msg ?? 'No se pudo resetear'}); });
  });

  $('#btnApplyWoo').on('click', async (e) => {
    e.preventDefault();
    const ok = await Swal.fire({icon:'question', title:'Aplicar en WooCommerce', html:'Creará/actualizará categorías según esta jerarquía.', showCancelButton:true, confirmButtonText:'Sí, aplicar', cancelButtonText:'Cancelar'});
    if (!ok.isConfirmed) return;
    swalLoading('Aplicando en Woo…');
    $.post(RUTA_APPLY)
      .done(resp => { Swal.close(); toast('success', resp?.msg ?? 'Listo'); })
      .fail(xhr  => { Swal.close(); Swal.fire({icon:'error',title:'Error', text:xhr.responseJSON?.error ?? xhr.responseJSON?.message ?? 'Fallo al aplicar'}); });
  });

  // ——— Nueva categoría ———
  const newCatModal = new bootstrap.Modal(document.getElementById('modalNewCat'));
  $('#btnNewCat').on('click', () => {
    const inst = $treeEl.jstree(true);
    const sel  = inst.get_selected(true);
    const $sel = $('select[name="parent_id"]').empty().append('<option value="">— MASTER (sin padre) —</option>');
    inst.get_json('#', { flat:true }).forEach(n => { $sel.append(`<option value="${n.id}">${inst.get_text(n.id)}</option>`); });
    if (sel.length === 1) $sel.val(sel[0].id);

    $('#formNewCat')[0].reset();
    newCatModal.show();
  });

  $('#formNewCat').on('submit', function (e) {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(this).entries());
    swalLoading('Creando categoría…');
    $.post(RUTA_STORE, payload)
      .done(() => { Swal.close(); newCatModal.hide(); $treeEl.jstree(true).refresh(); toast('success','Categoría creada'); })
      .fail(xhr => { Swal.close(); Swal.fire({icon:'error', title:'No se pudo crear', text:xhr.responseJSON?.message ?? 'Error'}); });
  });
});
</script>
@endpush
