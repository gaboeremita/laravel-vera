<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description');
            $table->unsignedInteger('base_price')->nullable();
            $table->text('contents')->nullable();
            $table->text('use_requirement')->nullable();
            $table->boolean('consumed_on_use')->default(false);
            $table->unsignedInteger('releases_credits')->default(0);
            $table->json('releases_items')->nullable();
            $table->timestamps();

            $table->unique(['world_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
