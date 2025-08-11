<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_history_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_history_id')->constrained()->onDelete('cascade');
            $table->string('sku');
            $table->enum('tipo', ['creado', 'actualizado']);
            $table->json('datos_nuevos');
            $table->json('datos_anteriores')->nullable(); // solo si fue actualizado
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_history_details');
    }
};
