<?php

namespace App\Http\Controllers;

use App\Models\SyncError;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;

class SyncErrorController extends Controller
{
    public function index()
    {
        Log::info('🟢 Vista de errores cargada.');
        $errores = SyncError::latest()->paginate(25);
        return view('sync_errors.index', compact('errores'));
    }

    public function exportExcel()
    {
        Log::info('📤 Exportando errores a Excel.');
        $errores = SyncError::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Sync History ID');
        $sheet->setCellValue('C1', 'SKU');
        $sheet->setCellValue('D1', 'Tipo de Error');
        $sheet->setCellValue('E1', 'Detalle');
        $sheet->setCellValue('F1', 'Fecha');

        // Filas
        $row = 2;
        foreach ($errores as $error) {
            $sheet->setCellValue("A{$row}", $error->id);
            $sheet->setCellValue("B{$row}", $error->sync_history_id);
            $sheet->setCellValue("C{$row}", $error->sku);
            $sheet->setCellValue("D{$row}", $error->tipo_error);
            $sheet->setCellValue("E{$row}", $error->detalle);
            $sheet->setCellValue("F{$row}", $error->created_at);
            $row++;
        }

        $fileName = 'errores_sync_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = storage_path("app/public/{$fileName}");

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return response()->download($filePath)->deleteFileAfterSend();
    }

    public function deleteAll()
    {
        Log::warning('🗑️ Eliminando todos los errores de sincronización.');
        SyncError::truncate();
        return redirect()->route('sync-errors.index')->with('success', 'Todos los errores han sido eliminados.');
    }
}
