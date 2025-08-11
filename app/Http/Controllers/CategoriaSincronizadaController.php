<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CategoriaSincronizada;

class CategoriaSincronizadaController extends Controller
{
    public function index($cliente)
    {
        // $categorias = CategoriaSincronizada::where('cliente', $cliente)
        //     ->orderByDesc('created_at')
        //     ->paginate(15);

        $categorias = CategoriaSincronizada::where('cliente', (string) $cliente)->paginate(50);

        return view('admin.categorias_sincronizadas.index', compact('categorias', 'cliente'));
    }
}
