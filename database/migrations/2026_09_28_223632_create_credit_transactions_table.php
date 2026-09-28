<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->foreignId('to_inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->string('from_name');
            $table->string('to_name');
            $table->unsignedInteger('amount');
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
