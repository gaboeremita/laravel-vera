<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'discord_message_id',
        'content',
        'thinking',
        'image',
        'expression',
        'tool_calls',
        'speaker_type',
        'speaker_id',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['emotion'];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'expression' => 'array',
        ];
    }

    /**
     * The emotion she showed, kept for readers of the old emotion field.
     */
    protected function emotion(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->expression['emotion'] ?? null);
    }

    /**
     * How a line was expressed, from what the tag parser found in it: the
     * emotion and pose she picked, the world action it went with, and every
     * tag removed from it, including ones she made up.
     *
     * @param  array{emotion?: ?string, pose?: ?string, tags?: array<string, array<int, string>>}  $parsed
     * @param  ?array<string, mixed>  $action
     * @param  array<int, string>  $strayTags
     * @return ?array{emotion?: string, pose?: string, action?: array<string, mixed>, tags?: array<string, array<int, string>>}
     */
    public static function expressionFrom(array $parsed, ?array $action = null, array $strayTags = []): ?array
    {
        $tags = $parsed['tags'] ?? [];
        if ($strayTags !== []) {
            $tags['stray'] = $strayTags;
        }

        $expression = array_filter([
            'emotion' => $parsed['emotion'] ?? null,
            'pose' => $parsed['pose'] ?? null,
            'action' => $action,
            'tags' => $tags,
        ], fn (mixed $value) => $value !== null && $value !== []);

        return $expression === [] ? null : $expression;
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Who said it, in a conversation between two assistants.
     */
    public function speaker(): MorphTo
    {
        return $this->morphTo();
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
