<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('lore_entries', function (Blueprint $table) {
			$table->id();
			$table->foreignId('lorebook_id')->constrained()->cascadeOnDelete();
			$table->string('title');
			$table->text('content');
			$table->vector('embedding', 768)->nullable();
			$table->json('keywords')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('lore_entries');
	}
};
