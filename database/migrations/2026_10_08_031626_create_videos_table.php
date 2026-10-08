<?php

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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_gen_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('job_id')->nullable();
            $table->text('prompt');
            $table->unsignedSmallInteger('duration')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->boolean('generate_audio')->nullable();
            $table->foreignId('first_frame_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->text('failure_reason')->nullable();
            $table->string('path')->nullable();
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
