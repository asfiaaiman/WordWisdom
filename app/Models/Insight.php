<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insight extends Model
{
	use HasFactory;

	protected $fillable = [
		'user_id',
		'word',
		'topic',
		'tone',
		'content',
	];

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}
}


