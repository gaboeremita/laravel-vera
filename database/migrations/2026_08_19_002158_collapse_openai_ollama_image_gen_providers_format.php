<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE image_gen_providers DROP CONSTRAINT IF EXISTS image_gen_providers_format_check');
        DB::statement("UPDATE image_gen_providers SET format = 'openai_compatible' WHERE format IN ('openai', 'ollama')");
        DB::statement("ALTER TABLE image_gen_providers ADD CONSTRAINT image_gen_providers_format_check CHECK (format IN ('openrouter', 'openai_compatible'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE image_gen_providers DROP CONSTRAINT IF EXISTS image_gen_providers_format_check');
        DB::statement("ALTER TABLE image_gen_providers ADD CONSTRAINT image_gen_providers_format_check CHECK (format IN ('openrouter', 'openai', 'ollama'))");
    }
};
