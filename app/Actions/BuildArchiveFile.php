<?php

namespace App\Actions;

use App\Builders\FileBuilder;
use App\Models\Archive;

class BuildArchiveFile
{
	public function __construct(private FileBuilder $fileBuilder) {}

	public function handle(Archive $archive): string
	{
		$this->fileBuilder
			->heading($archive->name, 1)
			->paragraph($archive->description)
			->heading('Entries', 2);

		foreach ($archive->entries as $entry) {
			$this->fileBuilder
				->heading($entry->title, 3)
				->paragraph($entry->content);

			if (! empty($entry->keywords)) {
				$this->fileBuilder->keyValue('Keywords', implode(', ', $entry->keywords));
			}

			if ($entry->tags->isNotEmpty()) {
				$this->fileBuilder->keyValue('Tags', $entry->tags->pluck('name')->implode(', '));
			}
		}

		return $this->fileBuilder->build();
	}
}
