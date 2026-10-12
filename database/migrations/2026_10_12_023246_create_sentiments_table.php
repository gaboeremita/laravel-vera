<?php

use App\Models\Assistant;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sentiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('values')->nullable();
            $table->timestamps();
        });

        DB::table('resident_sentiments')
            ->join('world_sessions', 'world_sessions.id', '=', 'resident_sentiments.world_session_id')
            ->join('world_user', 'world_user.id', '=', 'world_sessions.world_user_id')
            ->join('world_residents', 'world_residents.id', '=', 'resident_sentiments.world_resident_id')
            ->select('resident_sentiments.*', 'world_user.user_id', 'world_residents.assistant_id')
            ->orderBy('resident_sentiments.id')
            ->get()
            ->each(function (object $row): void {
                $conversation = [
                    'world_session_id' => $row->world_session_id,
                    'owner_type' => (new User)->getMorphClass(),
                    'owner_id' => $row->user_id,
                    'counterpart_type' => (new Assistant)->getMorphClass(),
                    'counterpart_id' => $row->assistant_id,
                ];
                $conversationId = DB::table('conversations')->where($conversation)->orderBy('id')->value('id')
                    ?? DB::table('conversations')->insertGetId([...$conversation, 'title' => 'New conversation', 'status' => 'active', 'created_at' => $row->created_at, 'updated_at' => $row->updated_at]);

                DB::table('sentiments')->insert([
                    'conversation_id' => $conversationId,
                    'values' => $row->values,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });

        Schema::drop('resident_sentiments');
    }

    public function down(): void
    {
        Schema::create('resident_sentiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_resident_id')->constrained()->cascadeOnDelete();
            $table->json('values')->nullable();
            $table->timestamps();

            $table->unique(['world_session_id', 'world_resident_id']);
        });

        DB::table('sentiments')
            ->join('conversations', 'conversations.id', '=', 'sentiments.conversation_id')
            ->join('world_sessions', 'world_sessions.id', '=', 'conversations.world_session_id')
            ->join('world_user', 'world_user.id', '=', 'world_sessions.world_user_id')
            ->join('world_residents', fn ($join) => $join->on('world_residents.world_id', '=', 'world_user.world_id')->on('world_residents.assistant_id', '=', 'conversations.counterpart_id'))
            ->select('sentiments.values', 'sentiments.created_at', 'sentiments.updated_at', 'conversations.world_session_id', 'world_residents.id as world_resident_id')
            ->get()
            ->each(fn (object $row) => DB::table('resident_sentiments')->insert((array) $row));

        Schema::drop('sentiments');
    }
};
