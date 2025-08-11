@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-4">Logs de API</h2>

    <form method="GET" action="{{ route('logs.index') }}" class="row g-3 mb-3">
        <div class="col-md-3">
            <input type="text" name="cliente" class="form-control" placeholder="Cliente"
                value="{{ request('cliente') }}">
        </div>
        <div class="col-md-3">
            <input type="text" name="endpoint" class="form-control" placeholder="Endpoint"
                value="{{ request('endpoint') }}">
        </div>
        <div class="col-md-3">
            <input type="date" name="fecha" class="form-control" value="{{ request('fecha') }}">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('logs.index') }}" class="btn btn-secondary">Limpiar</a>
            <a href="{{ route('logs.export', request()->query()) }}" class="btn btn-success">Exportar a Excel</a>
        </div>
    </form>

    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Endpoint</th>
                <th>Método</th>
                <th>IP</th>
                <th>Token</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->cliente_nombre }}</td>
                    <td>{{ $log->endpoint }}</td>
                    <td>{{ $log->method }}</td>
                    <td>{{ $log->ip }}</td>
                    <td>{{ $log->api_token }}</td>
                    <td>{{ $log->fecha }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No hay registros</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $logs->withQueryString()->links() }}
</div>
@endsection
