<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('object_id');
            $table->string('activity_id');
            $table->foreignId('required_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->boolean('consumes_required')->default(false);
            $table->unsignedInteger('cost')->default(0);
            $table->unsignedInteger('gives_credits')->default(0);
            $table->json('gives_items')->nullable();
            $table->text('requirement')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'object_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_terms');
    }
};
