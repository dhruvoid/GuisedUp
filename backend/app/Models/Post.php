<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'image_url',
        'authenticity_score',
        'pinecone_vector_id',
    ];

    protected function casts(): array
    {
        return [
            'authenticity_score' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }

    /**
     * Get the reaction count for this post.
     */
    public function getReactionCountAttribute(): int
    {
        return $this->interactions()->where('type', 'reaction')->count();
    }

    /**
     * Get the view count for this post.
     */
    public function getViewCountAttribute(): int
    {
        return $this->interactions()->where('type', 'view')->count();
    }
}
