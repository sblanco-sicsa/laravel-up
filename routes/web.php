<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportLogController;
use App\Http\Controllers\CategoriaSincronizadaController;
use App\Http\Controllers\SyncHistoryController;
use App\Http\Controllers\SyncErrorController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/logs', [ExportLogController::class, 'index'])->name('logs.index');
Route::get('/logs/export', [ExportLogController::class, 'export'])->name('logs.export');

Route::get('/admin/categorias-sincronizadas/{cliente}', [CategoriaSincronizadaController::class, 'index'])
    ->name('categorias.sincronizadas')
    ->middleware('web'); // Usa auth si es necesario


Route::get('/historial-sincronizaciones', [SyncHistoryController::class, 'index'])->name('sync-history.index');

Route::get('/sync-errors', [SyncErrorController::class, 'index'])->name('sync-errors.index');
Route::get('/sync-errors/export/excel', [SyncErrorController::class, 'exportExcel'])->name('sync-errors.export');


Route::delete('/sync-errors/delete-all', [SyncErrorController::class, 'deleteAll'])->name('sync-errors.delete-all');
Route::get('/sync/{sync}/stock-cero', [SyncHistoryController::class, 'descargarStockCero'])->name('sync.stock_cero');

Route::get('/historial-sincronizaciones/{sync}/stock-cero.csv', [SyncHistoryController::class, 'descargarStockCero'])->name('sync.stock_cero');


