<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sirett_items', function (Blueprint $t) {
            $t->id();
            $t->string('cliente', 100)->index();
            $t->string('sku')->nullable();
            $t->string('codigo')->nullable();
            $t->string('sku_key', 190)->index();   // normalizado
            $t->string('familia')->nullable();
            $t->string('familia_sirett')->nullable();
            $t->string('descripcion')->nullable();
            $t->timestamps();

            $t->unique(['cliente','sku_key']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('sirett_items');
    }
};
