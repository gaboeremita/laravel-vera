<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE voice_providers DROP CONSTRAINT IF EXISTS voice_providers_format_check');
        DB::statement("ALTER TABLE voice_providers ADD CONSTRAINT voice_providers_format_check CHECK (format IN ('openai_compatible', 'openai_tts'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE voice_providers DROP CONSTRAINT IF EXISTS voice_providers_format_check');
        DB::statement("ALTER TABLE voice_providers ADD CONSTRAINT voice_providers_format_check CHECK (format IN ('openai_compatible'))");
    }
};
