<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedTinyInteger('fill_level')->default(0); // 0-100
            $table->string('status')->default('vide')->index();     // vide | collecte_en_cours | plein
            $table->string('municipality_code')->nullable()->index(); // code de la mairie responsable
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bins');
    }
};
