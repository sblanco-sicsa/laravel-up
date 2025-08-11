<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('cliente_nombre');
            $table->string('endpoint');
            $table->string('method', 10);
            $table->ipAddress('ip');
            $table->string('api_token', 100)->nullable();
            $table->timestamp('fecha')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('api_logs');
    }
};
