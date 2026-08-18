<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copies existing Settings.data.discord.channels entries into the new
     * discord_servers/discord_channels/assistant_discord_channels tables so
     * nothing configured before this migration is lost.
     */
    public function up(): void
    {
        $rows = DB::table('settings')->whereNotNull('data')->get();

        foreach ($rows as $row) {
            $data = json_decode($row->data, true);
            $channels = $data['discord']['channels'] ?? [];

            if (empty($channels)) {
                continue;
            }

            $assistantUserId = DB::table('assistant_user')
                ->where('user_id', $row->user_id)
                ->where('assistant_id', $row->assistant_id)
                ->value('id');

            if (! $assistantUserId) {
                continue;
            }

            foreach ($channels as $channel) {
                DB::table('discord_servers')->updateOrInsert(
                    ['discord_guild_id' => $channel['guild_id']],
                    ['name' => $channel['guild_name'], 'created_at' => now(), 'updated_at' => now()]
                );

                $serverId = DB::table('discord_servers')
                    ->where('discord_guild_id', $channel['guild_id'])
                    ->value('id');

                DB::table('discord_channels')->updateOrInsert(
                    ['discord_channel_id' => $channel['channel_id']],
                    [
                        'discord_server_id' => $serverId,
                        'name' => $channel['channel_name'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $channelId = DB::table('discord_channels')
                    ->where('discord_channel_id', $channel['channel_id'])
                    ->value('id');

                DB::table('assistant_discord_channels')->updateOrInsert(
                    [
                        'assistant_user_id' => $assistantUserId,
                        'discord_channel_id' => $channelId,
                    ],
                    [
                        'trigger_mode' => $channel['trigger_mode'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Data migration only; nothing structural to reverse.
     */
    public function down(): void
    {
        //
    }
};
