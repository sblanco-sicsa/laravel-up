<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('client_feature_flags', function (Blueprint $t) {
            $t->id();
            $t->string('cliente', 100)->index();
            $t->string('feature_key', 100)->index(); // ej: use_promos
            $t->boolean('enabled')->default(false);
            $t->json('meta')->nullable();
            $t->timestamps();

            $t->unique(['cliente','feature_key']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('client_feature_flags');
    }
};
