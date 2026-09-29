<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('starting_inventories')->where('holder', 'resident')->update(['credits' => null]);
        DB::table('inventories')->where('holder', 'resident')->update(['credits' => null]);
    }

    public function down(): void
    {
        //
    }
};
