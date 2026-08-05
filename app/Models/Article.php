<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'author_id',
        'title',
        'content',
        'status',
        'approval_level',
        'sensitive_words_hit',
        'reject_reason',
        'current_auditor_id',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'sensitive_words_hit' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // 状态常量
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_EDITOR_APPROVED = 'editor_approved';
    const STATUS_CHIEF_APPROVED = 'chief_approved';
    const STATUS_PUBLISHED = 'published';
    const STATUS_REJECTED = 'rejected';

    // 审核级别常量
    const LEVEL_NONE = 'none';
    const LEVEL_EDITOR = 'editor';
    const LEVEL_SUPERVISOR = 'supervisor';
    const LEVEL_FINAL = 'final';

    // 关联
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function currentAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_auditor_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function sensitiveWordHits(): HasMany
    {
        return $this->hasMany(SensitiveWordHit::class);
    }

    // 状态判断辅助方法
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
