<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'auditor_id',
        'action',
        'from_level',
        'to_level',
        'result_status',
        'comment',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    // 操作类型常量
    const ACTION_APPROVED = 'approved';
    const ACTION_REJECTED = 'rejected';
    const ACTION_SUBMITTED = 'submitted';
    const ACTION_CLAIMED = 'claimed';

    // 关联
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
