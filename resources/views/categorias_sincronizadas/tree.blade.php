@extends('layouts.app') {{-- o el layout que uses --}}

@section('title', 'Categorías (Árbol)')

@php
  // Incluye dominio + subcarpeta real (ej. https://api.server.../laravel-up/public)
  $BASE = rtrim(request()->getSchemeAndHttpHost() . request()->getBaseUrl(), '/');
@endphp

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




      <button id="btnExpand" class="btn btn-outline-primary">
      <i class="bi bi-arrows-expand me-1"></i> Expandir todo
      </button>
      <button id="btnCollapse" class="btn btn-outline-primary">
      <i class="bi bi-arrows-collapse me-1"></i> Colapsar todo
      </button>
      <button id="btnReset" class="btn btn-outline-danger">
      <i class="bi bi-arrow-counterclockwise me-1"></i> Resetear a jerarquía de Woo
      </button>
      <button id="btnMakeMaster" class="btn btn-success">
      <i class="bi bi-arrow-bar-up me-1"></i> Hacer master
      </button>
      <button id="btnApplyWoo" type="button" class="btn btn-primary">
      <i class="bi bi-cloud-upload me-1"></i> Aplicar jerarquía en Woo
      </button>

    </div>
    </div>

    <div class="card shadow-sm">
    <div class="card-body">
      <div id="catTree"></div>
      <small class="text-muted d-block mt-3">
      Arrastra para cambiar padre. Soltar en la raíz (área superior) la marca como <strong>Master</strong>.
      </small>
    </div>
    </div>
  </div>
@endsection

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jstree@3.3.15/dist/themes/default/style.min.css">
@endpush

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/jstree@3.3.15/dist/jstree.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
    const BASE = @json($BASE);
    const cliente = @json($cliente);

    const RUTA_TREE = BASE + @json(route('categorias.api.tree', ['cliente' => $cliente], false));
    const RUTA_MOVE = BASE + @json(route('categorias.api.move', ['cliente' => $cliente], false));
    const RUTA_RESET = BASE + @json(route('categorias.api.reset', ['cliente' => $cliente], false));

    console.log({ BASE, RUTA_TREE, RUTA_MOVE, RUTA_RESET }); // 👈 verifica que no estén duplicadas subcarpetas

    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF } });

    const $treeEl = $('#catTree');

    // Init con data como función para capturar errores
    $treeEl.jstree({
      core: {
      check_callback: true,
      multiple: false,
      themes: { stripes: true },
      data: function (node, cb) {
        $.getJSON(RUTA_TREE)
        .done(function (resp) {
          if (!Array.isArray(resp)) {
          console.error('API TREE no devolvió array:', resp);
          alert('La API de categorías no devolvió un arreglo JSON.');
          cb([]); // evita crash
          return;
          }
          cb(resp);
        })
        .fail(function (xhr) {
          console.error('Fallo API TREE', xhr.status, xhr.responseText?.slice(0, 300));
          alert('No se pudo cargar el árbol de categorías.');
          cb([]); // evita crash
        });
      }
      },
      plugins: ['dnd', 'wholerow', 'state', 'types'],
      types: {
      master: { icon: 'bi bi-folder-fill text-primary' },
      child: { icon: 'bi bi-folder2' },
      default: { icon: 'bi bi-folder' }
      }
    })
      .on('error.jstree', function (e, data) {
      console.error('jsTree error:', data);
      })
      .on('move_node.jstree', function (e, data) {
      const nuevoParent = (data.parent === '#') ? null : data.parent;
      $.post(RUTA_MOVE, {
        id: data.node.id,
        parent: nuevoParent,
        position: data.position
      })
        .done(() => {
        const inst = $treeEl.jstree(true);
        const newType = nuevoParent ? 'child' : 'master';
        inst.set_type(data.node, newType);

        const $li = inst.get_node(data.node, true);
        $li.removeClass('is-master is-child')
          .addClass(nuevoParent ? 'is-child' : 'is-master')
          .attr('title', nuevoParent ? 'Categoría hija' : 'Categoría master');

        console.log('Jerarquía actualizada');
        })
        .fail((xhr) => {
        alert(xhr.responseJSON?.msg ?? xhr.responseJSON?.error ?? 'No se pudo mover el nodo');
        $treeEl.jstree(true).refresh(); // revertir
        });
      });

    $('#btnExpand').on('click', () => $treeEl.jstree('open_all'));
    $('#btnCollapse').on('click', () => $treeEl.jstree('close_all'));

    $('#btnReset').on('click', () => {
      if (!confirm('¿Seguro que deseas resetear la jerarquía manual a la estructura de Woo?')) return;
      $.post(RUTA_RESET)
      .done(() => $treeEl.jstree(true).refresh())
      .fail((xhr) => alert(xhr.responseJSON?.msg ?? 'No se pudo resetear'));
    });

    // Hacer master (mover a raíz)
    $('#btnMakeMaster').on('click', () => {
      const inst = $treeEl.jstree(true);
      const sel = inst.get_selected(true);
      if (!sel.length) { alert('Selecciona una categoría primero.'); return; }
      if (sel.length > 1) { alert('Selecciona solo una categoría.'); return; }
      const node = sel[0];
      if (node.parent === '#') { alert('Esta categoría ya es master.'); return; }
      inst.move_node(node, '#', 'last'); // dispara move_node y guarda
    });


    const RUTA_APPLY = @json(route('woo.categories.applyHierarchy', ['cliente' => $cliente]));
    //const RUTA_APPLY = BASE + @json(route('woo.categories.applyHierarchy', ['cliente' => $cliente], false));
    console.log({ RUTA_APPLY }); // para verificar que incluye /laravel-up/public

    $('#btnApplyWoo').on('click', (e) => {
      e.preventDefault();               // <- evita submit/navegación
      if (!confirm('...')) return;
      $.post(RUTA_APPLY)
      .done(resp => alert(resp?.msg ?? 'Jerarquía aplicada en WooCommerce'))
      .fail(xhr => alert(xhr.responseJSON?.error ?? xhr.responseJSON?.message ?? 'No se pudo aplicar la jerarquía en Woo'));
    });



    });
  </script>
@endpush