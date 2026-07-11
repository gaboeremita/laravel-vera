<?php

use App\Enums\AiProviderFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->string('name');
			$table->string('url');
			$table->text('api_key');
			$table->text('prompt')->nullable();
			$table->json('config_schema')->nullable();
			$table->enum('format', array_column(AiProviderFormat::cases(), 'value'))
				->default(AiProviderFormat::Generic->value);
			$table->unique(['user_id', 'name']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
