<?php

use App\Enums\VideoGenProviderFormat;
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
        Schema::create('video_gen_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->text('api_key')->nullable();
            $table->json('prompt')->nullable();
            $table->json('config_schema')->nullable();
            $table->enum('format', array_column(VideoGenProviderFormat::cases(), 'value'))
                ->default(VideoGenProviderFormat::OpenRouter->value);
            $table->unique(['user_id', 'name']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_gen_providers');
    }
};
