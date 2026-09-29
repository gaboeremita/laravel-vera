<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('starting_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('starting_inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->boolean('for_sale')->default(false);
            $table->boolean('takeable')->default(false);
            $table->timestamps();

            $table->unique(['starting_inventory_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('starting_inventory_items');
    }
};
