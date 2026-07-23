<?php

use App\Enums\VoiceProviderFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('voice_providers', function (Blueprint $table) {
			$table->id();
			$table->string('name')->unique();
			$table->string('url');
			$table->text('api_key')->nullable();
			$table->enum('format', array_column(VoiceProviderFormat::cases(), 'value'));
			$table->text('instructions')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('voice_providers');
	}
};
