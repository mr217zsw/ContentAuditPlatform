<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SensitiveWord extends Model
{
    use HasFactory;

    protected $fillable = [
        'word',
        'level',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 风险级别常量
    const LEVEL_LOW = 'low';
    const LEVEL_MEDIUM = 'medium';
    const LEVEL_HIGH = 'high';
    const LEVEL_FORBIDDEN = 'forbidden';

    // 范围内查询活跃词
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
