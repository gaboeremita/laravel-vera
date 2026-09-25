<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const USER = 'App\\Models\\User';

    private const ASSISTANT = 'App\\Models\\Assistant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->nullableMorphs('owner');
            $table->nullableMorphs('counterpart');
            $table->string('status')->default('active');
            $table->timestamp('resumed_at')->nullable();
        });

        DB::table('conversations')
            ->join('assistant_user', 'assistant_user.id', '=', 'conversations.assistant_user_id')
            ->select('conversations.id', 'assistant_user.user_id', 'assistant_user.assistant_id')
            ->orderBy('conversations.id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('conversations')->where('id', $row->id)->update([
                        'owner_type' => self::USER,
                        'owner_id' => $row->user_id,
                        'counterpart_type' => self::ASSISTANT,
                        'counterpart_id' => $row->assistant_id,
                    ]);
                }
            });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assistant_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assistant_user_id')->nullable()->constrained('assistant_user')->cascadeOnDelete();
        });

        DB::table('conversations')
            ->where('owner_type', self::USER)
            ->where('counterpart_type', self::ASSISTANT)
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $pivotId = DB::table('assistant_user')->where('user_id', $row->owner_id)->where('assistant_id', $row->counterpart_id)->value('id');
                    DB::table('conversations')->where('id', $row->id)->update(['assistant_user_id' => $pivotId]);
                }
            });

        DB::table('conversations')->whereNull('assistant_user_id')->delete();

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropMorphs('owner');
            $table->dropMorphs('counterpart');
            $table->dropColumn(['status', 'resumed_at']);
        });
    }
};
