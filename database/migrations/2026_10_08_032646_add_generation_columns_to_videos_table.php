<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generated videos share the table with emotion videos; their generation
     * columns stay null on emotion rows, and path stays null until a generated
     * video is downloaded.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
            $table->foreignId('video_gen_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->nullable();
            $table->string('job_id')->nullable();
            $table->text('prompt')->nullable();
            $table->unsignedSmallInteger('duration')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->boolean('generate_audio')->nullable();
            $table->foreignId('first_frame_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->text('failure_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_gen_model_id');
            $table->dropConstrainedForeignId('first_frame_image_id');
            $table->dropColumn(['status', 'job_id', 'prompt', 'duration', 'aspect_ratio', 'generate_audio', 'failure_reason']);
            $table->string('path')->nullable(false)->change();
        });
    }
};
