<?php

namespace App\Events;

use App\Models\Article;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Article $article,
    ) {}

    public function broadcastOn(): array
    {
        return [
            // 通知编辑级别的审核员
            new Channel('audit.level.editor'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'article.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'article_id' => $this->article->id,
            'title' => $this->article->title,
            'author' => $this->article->author?->name,
            'sensitive_words_hit' => $this->article->sensitive_words_hit,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
