<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
	protected $fillable = [
		'path',
		'disk',
		'mime_type',
		'size',
		'original_name',
	];

	public function videoable(): MorphTo
	{
		return $this->morphTo();
	}

	public function getUrlAttribute(): string
	{
		return Storage::disk($this->disk)->url($this->path);
	}
}
