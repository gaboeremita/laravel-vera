<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ai_providers DROP CONSTRAINT IF EXISTS ai_providers_format_check');
        DB::statement("ALTER TABLE ai_providers ADD CONSTRAINT ai_providers_format_check CHECK (format IN ('generic', 'anthropic', 'gemini'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ai_providers DROP CONSTRAINT IF EXISTS ai_providers_format_check');
        DB::statement("ALTER TABLE ai_providers ADD CONSTRAINT ai_providers_format_check CHECK (format IN ('generic', 'anthropic'))");
    }
};
