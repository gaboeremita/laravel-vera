<?php

namespace App\Models;

use App\Enums\AiProviderFormat;
use Database\Factories\AiProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'url', 'api_key', 'prompt', 'config_schema', 'format'])]
class AiProvider extends Model
{
    /** @use HasFactory<AiProviderFactory> */
    use HasFactory;

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
            'config_schema' => 'array',
            'format' => AiProviderFormat::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }
}
