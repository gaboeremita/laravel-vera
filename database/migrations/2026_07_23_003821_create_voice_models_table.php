<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('voice_models', function (Blueprint $table) {
			$table->id();
			$table->foreignId('provider_id')->constrained('voice_providers')->cascadeOnDelete();
			$table->string('name');
			$table->string('endpoint');
			$table->json('voices');
			$table->json('config')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('voice_models');
	}
};
