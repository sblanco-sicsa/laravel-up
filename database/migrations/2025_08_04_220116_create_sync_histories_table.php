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
        Schema::create('sync_histories', function (Blueprint $table) {
            $table->id();
            $table->string('cliente'); // nombre del cliente
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('total_creados')->default(0);
            $table->integer('total_actualizados')->default(0);
            $table->integer('total_omitidos')->default(0);
            $table->integer('total_fallidos_categoria')->default(0);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_histories');
    }
};
