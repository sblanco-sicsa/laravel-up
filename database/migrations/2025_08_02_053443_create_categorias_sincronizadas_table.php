<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categoria_sincronizadas', function (Blueprint $table) {
            $table->id();
            $table->string('cliente');
            $table->string('nombre');
            $table->unsignedBigInteger('woocommerce_id')->nullable();
            $table->json('respuesta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_sincronizadas');
    }
};

