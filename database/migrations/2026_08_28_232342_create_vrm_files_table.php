<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vrm_files', function (Blueprint $table) {
            $table->id();
            $table->morphs('vrmable');
            $table->string('path');
            $table->string('disk');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->unique(['vrmable_type', 'vrmable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vrm_files');
    }
};
