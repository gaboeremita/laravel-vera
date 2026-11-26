<?php

namespace App\Models;

use Database\Factories\AiModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_id', 'name', 'endpoint', 'thinking_key', 'prompt', 'config', 'additional_config', 'supports_tools', 'cache_marks', 'conversation_key_field'])]
class AiModel extends Model
{
    /** @use HasFactory<AiModelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'additional_config' => 'array',
            'supports_tools' => 'boolean',
            'cache_marks' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    /**
     * A copy of the model that reasons at the given effort, for calls that need
     * little thought; a model whose config sets no reasoning effort comes back as it is.
     */
    public function withReasoningEffort(string $effort): self
    {
        $config = $this->config ?? [];
        if (! isset($config['reasoning']['effort'])) {
            return $this;
        }

        $copy = $this->replicate();
        $copy->config = array_replace_recursive($config, ['reasoning' => ['effort' => $effort]]);

        return $copy;
    }
}
