<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request; // ✅ Esto es lo que te faltaba
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Response;

class ExportLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = DB::table('api_logs')
            ->when($request->cliente, fn($q) => $q->where('cliente_nombre', 'like', '%' . $request->cliente . '%'))
            ->when($request->endpoint, fn($q) => $q->where('endpoint', 'like', '%' . $request->endpoint . '%'))
            ->when($request->fecha, fn($q) => $q->whereDate('fecha', $request->fecha))
            ->orderBy('fecha', 'desc')
            ->paginate(20);

        return view('logs.index', compact('logs'));
    }

    public function export(Request $request)
    {
        $logs = DB::table('api_logs')
            ->when($request->cliente, fn($q) => $q->where('cliente_nombre', 'like', '%' . $request->cliente . '%'))
            ->when($request->endpoint, fn($q) => $q->where('endpoint', 'like', '%' . $request->endpoint . '%'))
            ->when($request->fecha, fn($q) => $q->whereDate('fecha', $request->fecha))
            ->orderBy('fecha', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Cliente', 'Endpoint', 'Método', 'IP', 'Token', 'Fecha']
        ], NULL, 'A1');

        $row = 2;
        foreach ($logs as $log) {
            $sheet->fromArray([
                $log->cliente_nombre ?? '',
                $log->endpoint ?? '',
                $log->method ?? '',
                $log->ip ?? '',
                $log->api_token ?? '',
                $log->fecha ?? '',
            ], NULL, 'A' . $row++);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'logs_filtrados_' . now()->format('Ymd_His') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

}
