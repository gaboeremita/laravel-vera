<?php

namespace App\Models;

use Database\Factories\EmotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Emotion extends Model
{
    /** @use HasFactory<EmotionFactory> */
    use HasFactory;

	protected $fillable = [
		'name',
		'restricted'
	];

	public function image(): MorphOne
	{
		return $this->morphOne(Image::class, 'imageable');
	}

	public function video(): MorphOne
	{
		return $this->morphOne(Video::class, 'videoable');
	}
}
