<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recycler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('waste_type'); // plastique, papier_carton, metal, verre, organique, electronique
            $table->decimal('quantity_kg', 6, 2);
            $table->string('photo_path')->nullable();
            $table->string('status')->default('en_attente')->index(); // en_attente|acceptee|refusee|recuperee|payee|annulee
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_offers');
    }
};
