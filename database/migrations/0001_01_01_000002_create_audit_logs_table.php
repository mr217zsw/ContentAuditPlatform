<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('auditor_id')->constrained('users')->cascadeOnDelete();

            $table->enum('action', ['approved', 'rejected', 'submitted', 'claimed'])
                ->comment('操作类型');
            $table->enum('from_level', ['none', 'editor', 'supervisor', 'final'])
                ->comment('审核前级别');
            $table->enum('to_level', ['none', 'editor', 'supervisor', 'final'])
                ->comment('审核后级别');
            $table->enum('result_status', ['draft', 'pending', 'editor_approved', 'chief_approved', 'published', 'rejected'])
                ->comment('操作后稿件状态');

            $table->text('comment')->nullable()->comment('审核意见');
            $table->json('snapshot')->nullable()->comment('稿件快照 {title, content}');

            $table->timestamps();

            $table->index('article_id');
            $table->index('auditor_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
