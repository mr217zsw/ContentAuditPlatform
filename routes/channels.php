<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

// 审核频道 - 审核员按级别订阅
Broadcast::channel('audit.level.{level}', function (User $user, string $level) {
    $roles = [
        'editor'   => 'editor',
        'supervisor' => 'supervisor',
        'final'    => 'final_approver',
    ];
    return $user->role === ($roles[$level] ?? null) || $user->role === 'admin';
});

// 稿件频道 - 作者可订阅自己稿件的审核进度
Broadcast::channel('article.{articleId}', function (User $user, int $articleId) {
    return App\Models\Article::where('id', $articleId)
        ->where('author_id', $user->id)
        ->exists() || in_array($user->role, ['admin', 'editor', 'supervisor', 'final_approver']);
});
