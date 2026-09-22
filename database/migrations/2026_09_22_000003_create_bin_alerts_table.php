<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bin_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();               // ALR-0001 (généré après création)
            $table->foreignId('bin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // agent ISACAM
            $table->string('photo_path')->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->default('en_attente')->index(); // en_attente|en_cours|traite
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bin_alerts');
    }
};
