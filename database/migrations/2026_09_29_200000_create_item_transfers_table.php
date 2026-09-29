<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->foreignId('to_inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->boolean('by_creator')->default(false);
            $table->timestamps();

            $table->index(['world_session_id', 'from_inventory_id', 'to_inventory_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_transfers');
    }
};
