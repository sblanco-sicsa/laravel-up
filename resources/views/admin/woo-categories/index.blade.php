@extends('layout.app')

@section('title', 'Categorías Woo')

@section('content')
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><i class="bi bi-tags-fill me-2"></i>Categorías Woo ({{ $cliente }})</h4>
    <div class="d-flex gap-2">
      <button id="btnSync" class="btn btn-primary">
      <i class="bi bi-arrow-repeat me-1"></i> Sincronizar categorías
      </button>
      <button id="btnDeleteZeros" class="btn btn-danger">
      <i class="bi bi-trash3 me-1"></i> Eliminar todas (count = 0)
      </button>
    </div>
    </div>

    <div id="alertBox"></div>

    <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm align-middle text-center small" id="tblCats">
        <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Slug</th>
          <th>Parent</th>
          <th>Count</th>
          <th>Acciones</th>
        </tr>
        </thead>
        <tbody><!-- rows via JS --></tbody>
      </table>
      </div>
    </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    const URLS = {
    data: @json(route('woo.categories.data', ['cliente' => $cliente])),
    delZero: @json(route('woo.categories.delete-zero.web', ['cliente' => $cliente])),
    delOne: (id) => @json(route('woo.categories.delete-one.web', ['cliente' => $cliente, 'id' => '__ID__'])).replace('__ID__', id),
    sync: @json(route('woo.categories.sync.web', ['cliente' => $cliente])),
    };

    const CSRF = document.querySelector('meta[name="csrf-token']")?.getAttribute('content');
  const headers = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(CSRF ? { 'X-CSRF-TOKEN': CSRF } : {})
    };

    const alertBox = document.getElementById('alertBox');
    function showAlert(msg, type = 'success') {
    alertBox.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
    }

    async function loadCategories() {
    const r = await fetch(URLS.data, { headers });
    if (!r.ok) { showAlert('Error cargando categorías', 'danger'); return; }
    const data = await r.json();
    const tbody = document.querySelector('#tblCats tbody');
    tbody.innerHTML = '';
    (data.categories || []).forEach(c => {
      const canDelete = Number(c.count) === 0;
      const btn = canDelete
      ? `<button class="btn btn-sm btn-danger btnDelRow" data-id="${c.id}">
        <i class="bi bi-trash3"></i>
       </button>`
      : `<button class="btn btn-sm btn-secondary" disabled title="Tiene productos">
        <i class="bi bi-ban"></i>
       </button>`;

      const tr = document.createElement('tr');
      tr.innerHTML = `
      <td>${c.id}</td>
      <td class="text-start">${c.name}</td>
      <td class="text-muted">${c.slug}</td>
      <td>${c.parent}</td>
      <td class="${canDelete ? 'text-danger fw-bold' : 'text-success fw-bold'}">${c.count}</td>
      <td>${btn}</td>
    `;
      tbody.appendChild(tr);
    });
    }

    async function deleteAllZeros() {
    if (!confirm('¿Eliminar TODAS las categorías con count = 0? Esta acción no se puede deshacer.')) return;
    const r = await fetch(URLS.delZero, { method: 'DELETE', headers });
    const data = await r.json();
    if (r.ok) {
      showAlert(`Eliminadas: ${(data.eliminadas || []).length}. Errores: ${(data.errores || []).length}`);
      await loadCategories();
    } else {
      showAlert(data.error || 'Error al eliminar', 'danger');
    }
    }

    async function deleteOne(id) {
    if (!confirm(`¿Eliminar la categoría #${id} (count=0)?`)) return;
    const r = await fetch(URLS.delOne(id), { method: 'DELETE', headers });
    const data = await r.json();
    if (r.ok) {
      showAlert('Categoría eliminada');
      await loadCategories();
    } else {
      showAlert(data.error || 'No se pudo eliminar', 'danger');
    }
    }

    async function syncCategories() {
    if (!confirm('Esto limpiará duplicados (count=0), renombrará (si aplica) y creará faltantes desde SiReTT. ¿Continuar?')) return;
    const r = await fetch(URLS.sync, { method: 'POST', headers, body: '{}' });
    const data = await r.json();
    if (r.ok) {
      showAlert('Sincronización completada');
      await loadCategories();
    } else {
      showAlert(data.error || 'Error en sincronización', 'danger');
    }
    }

    document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.btnDelRow');
    if (btn) deleteOne(btn.getAttribute('data-id'));
    });

    document.getElementById('btnDeleteZeros').addEventListener('click', deleteAllZeros);
    document.getElementById('btnSync').addEventListener('click', syncCategories);

    loadCategories();
  </script>
@endpush