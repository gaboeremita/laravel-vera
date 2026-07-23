<?php

namespace App\Models;

use App\Enums\VoiceProviderFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'url', 'api_key', 'format', 'instructions', 'prompt'])]
class VoiceProvider extends Model
{
	protected $hidden = ['api_key'];

	protected $appends = ['has_key'];

	protected function hasKey(): Attribute
	{
		return Attribute::get(fn () => ! empty($this->api_key));
	}

	protected function casts(): array
	{
		return [
			'api_key' => 'encrypted',
			'format' => VoiceProviderFormat::class,
			'prompt' => 'array',
		];
	}

	public function models(): HasMany
	{
		return $this->hasMany(VoiceModel::class, 'provider_id');
	}
}
