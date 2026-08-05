<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SensitiveWordHit extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'sensitive_word_id',
        'word',
        'position',
    ];

    // 关联
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function sensitiveWord(): BelongsTo
    {
        return $this->belongsTo(SensitiveWord::class);
    }
}
