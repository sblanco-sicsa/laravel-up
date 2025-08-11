<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSyncErrorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('sync_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_history_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('sku')->nullable();
            $table->string('tipo_error');
            $table->text('detalle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_errors');
    }
}
