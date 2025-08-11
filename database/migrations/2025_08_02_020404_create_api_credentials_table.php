<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('cliente_nombre'); // Relacionado a un solo cliente
            $table->string('nombre'); // sirett, woocommerce, telegram
            $table->string('base_url')->nullable();
            $table->string('user')->nullable();
            $table->string('password')->nullable();
            $table->string('extra')->nullable(); // bid o cualquier extra
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
    }
};
