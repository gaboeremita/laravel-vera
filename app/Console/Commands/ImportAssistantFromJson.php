<?php

namespace App\Console\Commands;

use App\Models\Assistant;
use Illuminate\Console\Command;
use function Laravel\Prompts\textarea;

class ImportAssistantFromJson extends Command
{
	protected $signature = 'assistant:import';

	protected $description = 'Import an assistant from a JSON prompt file';

	public function handle(): int
	{

		$name = $this->ask('Name');
		$slug = $this->ask('Slug');
		$description = textarea(label: 'Description');
		$openingMessage = textarea(label: 'Opening message');

		$config = null;
		while (! $config) {
			$path = $this->ask('Path to JSON prompt file');

			if (! file_exists($path)) {
				$this->error("File not found: {$path}");
				if (! $this->confirm('Try again?')) {
					$this->info('Understood. Exiting');
					return self::FAILURE;
				}
				continue;
			}

			try {
				$config = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
			} catch (\JsonException $e) {
				$this->error('Invalid JSON: ' . $e->getMessage());
				if (! $this->confirm('Try again?')) {
					$this->info('Understood. Exiting');
					return self::FAILURE;
				}
			}
		}

		if (! $this->confirm('Confirm?')) {
			$this->info('Aborted.');
			return self::SUCCESS;
		}

		Assistant::updateOrCreate(
			['slug' => $slug],
			[
				'name' => $name,
				'description' => $description,
				'prompt' => $config,
				'opening_message' => $openingMessage
			]
		);

		$this->info("Assistant '{$name}' imported.");

		return self::SUCCESS;
	}
}