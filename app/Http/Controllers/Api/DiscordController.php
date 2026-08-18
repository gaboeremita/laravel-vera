<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistantDiscordChannel;
use App\Models\AssistantDiscordServer;
use App\Models\DiscordChannel;
use App\Models\DiscordServer;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DiscordController extends Controller
{
	use ResolvesAssistantUser;

	public function discovery(Request $request, int $assistant): JsonResponse
	{
		$apiConfig = config('ai.discord');
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		try {
			$response = Http::timeout($apiConfig['timeout'])
				->withHeaders(['X-Internal-Secret' => $apiConfig['api_secret']])
				->get("{$apiConfig['api_url']}/assistants/{$assistant}/discovery");
		} catch (\Throwable $e) {
			return response()->json(['guilds' => [], 'message' => 'discord-api is unreachable'], 502);
		}

		if ($response->notFound()) {
			return response()->json(['guilds' => [], 'message' => 'No Discord bot configured for this assistant']);
		}

		if ($response->failed()) {
			return response()->json(['guilds' => [], 'message' => 'discord-api request failed'], 502);
		}

		$guilds = $response->json('guilds', []);
		$servers = $this->syncGuilds($guilds);

		$serverPrompts = AssistantDiscordServer::where('assistant_user_id', $assistantUser->id)
			->whereIn('discord_server_id', $servers->pluck('id'))
			->get()
			->keyBy('discord_server_id');

		$channelConfigs = AssistantDiscordChannel::where('assistant_user_id', $assistantUser->id)
			->whereIn('discord_channel_id', $servers->flatMap->channels->pluck('id'))
			->get()
			->keyBy('discord_channel_id');

		foreach ($guilds as &$guild) {
			$server = $servers->firstWhere('discord_guild_id', $guild['id']);
			$guild['prompt'] = $serverPrompts->get($server->id)?->prompt ?? [];

			foreach ($guild['channels'] as &$channel) {
				$dbChannel = $server->channels->firstWhere('discord_channel_id', $channel['id']);
				$channelConfig = $channelConfigs->get($dbChannel->id);
				$channel['trigger_mode'] = $channelConfig?->trigger_mode ?? 'off';
				$channel['prompt'] = $channelConfig?->prompt ?? [];
			}
		}

		return response()->json(['guilds' => $guilds]);
	}

	public function updateServerPrompt(Request $request, int $assistant, string $guildId): JsonResponse
	{
		$validated = $request->validate(['prompt' => ['present', 'array']]);

		$assistantUser = $this->resolveAssistantUser($request, $assistant);
		$server = DiscordServer::where('discord_guild_id', $guildId)->firstOrFail();

		$pivot = AssistantDiscordServer::updateOrCreate(
			['assistant_user_id' => $assistantUser->id, 'discord_server_id' => $server->id],
			['prompt' => $validated['prompt']],
		);

		return response()->json(['prompt' => $pivot->prompt]);
	}

	public function updateChannelPrompt(Request $request, int $assistant, string $channelId): JsonResponse
	{
		$validated = $request->validate(['prompt' => ['present', 'array']]);

		$assistantUser = $this->resolveAssistantUser($request, $assistant);
		$channel = DiscordChannel::where('discord_channel_id', $channelId)->firstOrFail();

		$pivot = AssistantDiscordChannel::where('assistant_user_id', $assistantUser->id)
			->where('discord_channel_id', $channel->id)
			->first();

		if (! $pivot) {
			return response()->json(['message' => 'Set a trigger mode for this channel before adding a prompt.'], 422);
		}

		$pivot->update(['prompt' => $validated['prompt']]);

		return response()->json(['prompt' => $pivot->prompt]);
	}

	/**
	 * Keep the internal discord_servers/discord_channels records in sync with what
	 * discord-api actually sees live, so per-assistant config can reference a stable
	 * internal id instead of raw Discord identifiers.
	 *
	 * @param  array<int, array{id: string, name: string, channels: array<int, array{id: string, name: string}>}>  $guilds
	 * @return Collection<int, DiscordServer>
	 */
	private function syncGuilds(array $guilds): Collection
	{
		return collect($guilds)->map(function (array $guild) {
			$server = DiscordServer::updateOrCreate(
				['discord_guild_id' => $guild['id']],
				['name' => $guild['name']],
			);

			$server->setRelation('channels', collect($guild['channels'])->map(
				fn (array $channel) => DiscordChannel::updateOrCreate(
					['discord_channel_id' => $channel['id']],
					['discord_server_id' => $server->id, 'name' => $channel['name']],
				)
			));

			return $server;
		});
	}
}
