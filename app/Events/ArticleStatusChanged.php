<?php

namespace App\Events;

use App\Models\Article;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Article $article,
        public string $action,
        public string $operatorName,
    ) {}

    public function broadcastOn(): array
    {
        return [
            // 按审核级别广播
            new Channel('audit.level.' . $this->getLevelChannel()),
            // 稿件专属频道
            new Channel('article.' . $this->article->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'article.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'article_id' => $this->article->id,
            'title' => $this->article->title,
            'status' => $this->article->status,
            'approval_level' => $this->article->approval_level,
            'action' => $this->action,
            'operator' => $this->operatorName,
            'sensitive_words_hit' => $this->article->sensitive_words_hit,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function getLevelChannel(): string
    {
        return match ($this->article->approval_level) {
            'editor' => 'editor',
            'supervisor' => 'supervisor',
            'final' => 'final',
            default => 'none',
        };
    }
}
