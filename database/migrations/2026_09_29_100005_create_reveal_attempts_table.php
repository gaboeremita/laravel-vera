<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reveal_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('world_resident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fact_topic', 120);
            $table->string('holder_name');
            $table->string('source');
            $table->text('reason')->nullable();
            $table->boolean('reviewed');
            $table->boolean('approved');
            $table->text('verdict')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reveal_attempts');
    }
};
