<?php

namespace App\Http\Controllers;

use App\Models\SyncHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SyncHistoryController extends Controller
{
    public function index(Request $request)
    {
        $sincronizaciones = SyncHistory::with('detalles')
            ->orderByDesc('id')
            ->paginate(10);

        // Enriquecemos para el modal de stock=0 (sin AJAX)
        $sincronizaciones->getCollection()->transform(function ($s) {
            $path = "exports/stock_cero_{$s->id}.csv";
            $s->has_stock_cero_csv = Storage::disk('local')->exists($path);
            $s->stock_cero_list = [];

            if ($s->has_stock_cero_csv) {
                try {
                    $contents = Storage::disk('local')->get($path);
                    $lines = preg_split("/\r\n|\n|\r/", $contents);
                    $lines = array_values(array_filter($lines, fn($x) => trim($x) !== ''));
                    if (!empty($lines) && strtolower(trim($lines[0])) === 'sku') array_shift($lines);
                    $s->stock_cero_list = $lines;
                } catch (\Throwable $e) {
                    $s->stock_cero_list = [];
                }
            }
            return $s;
        });

        return view('sync_history.index', compact('sincronizaciones'));
    }

    public function descargarStockCero(SyncHistory $sync)
    {
        $path = "exports/stock_cero_{$sync->id}.csv";
        abort_unless(Storage::disk('local')->exists($path), 404, 'Archivo no disponible para esta sincronización.');
        return Storage::download($path, "stock_cero_sirett_sync_{$sync->id}.csv");
    }
}
