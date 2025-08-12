<?php

namespace App\Http\Controllers;

use App\Models\SyncHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SyncHistoryController extends Controller
{
    public function index(Request $request)
    {
        // Si no hay fecha seleccionada, usar la fecha actual
        $hoy = now()->format('Y-m-d');
        $desde = $request->input('desde', $hoy);
        $hasta = $request->input('hasta', $hoy);

        // Validar formato
        $request->merge(['desde' => $desde, 'hasta' => $hasta]);
        $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = SyncHistory::with('detalles')->orderByDesc('id');

        // Filtros por rango (inclusive)
        $query->when($desde, function ($q) use ($desde) {
            $q->whereDate('started_at', '>=', $desde);
        });
        $query->when($hasta, function ($q) use ($hasta) {
            $q->where('started_at', '<=', \Carbon\Carbon::parse($hasta)->endOfDay());
        });

        $sincronizaciones = $query
            ->paginate(10)
            ->appends($request->only(['desde', 'hasta']));

        // Enriquecer stock=0
        $sincronizaciones->getCollection()->transform(function ($s) {
            $path = "exports/stock_cero_{$s->id}.csv";
            $s->has_stock_cero_csv = \Illuminate\Support\Facades\Storage::disk('local')->exists($path);
            $s->stock_cero_list = [];

            if ($s->has_stock_cero_csv) {
                try {
                    $contents = \Illuminate\Support\Facades\Storage::disk('local')->get($path);
                    $lines = preg_split("/\r\n|\n|\r/", $contents);
                    $lines = array_values(array_filter($lines, fn($x) => trim($x) !== ''));
                    if (!empty($lines) && strtolower(trim($lines[0])) === 'sku')
                        array_shift($lines);
                    $s->stock_cero_list = $lines;
                } catch (\Throwable $e) {
                    $s->stock_cero_list = [];
                }
            }
            return $s;
        });

        return view('sync_history.index', compact('sincronizaciones', 'desde', 'hasta'));
    }


    public function descargarStockCero(SyncHistory $sync)
    {
        $path = "exports/stock_cero_{$sync->id}.csv";
        abort_unless(Storage::disk('local')->exists($path), 404, 'Archivo no disponible para esta sincronización.');
        return Storage::download($path, "stock_cero_sirett_sync_{$sync->id}.csv");
    }
}
