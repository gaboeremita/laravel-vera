<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE image_gen_providers ALTER COLUMN prompt TYPE json USING to_jsonb(prompt)');
        DB::statement('ALTER TABLE image_gen_models ALTER COLUMN prompt TYPE json USING to_jsonb(prompt)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE image_gen_providers ALTER COLUMN prompt TYPE text USING prompt::text');
        DB::statement('ALTER TABLE image_gen_models ALTER COLUMN prompt TYPE text USING prompt::text');
    }
};
