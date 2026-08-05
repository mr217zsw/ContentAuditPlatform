<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200)->comment('稿件标题');
            $table->text('content')->comment('稿件正文');
            $table->enum('status', ['draft', 'pending', 'editor_approved', 'chief_approved', 'published', 'rejected'])
                ->default('draft')
                ->comment('draft=草稿, pending=待初审, editor_approved=编辑通过, chief_approved=终审通过, published=已发布, rejected=驳回');
            $table->enum('approval_level', ['none', 'editor', 'supervisor', 'final'])
                ->default('none')
                ->comment('当前审批级别');

            // 敏感词命中记录 (JSON)
            $table->json('sensitive_words_hit')->nullable()
                ->comment('命中的敏感词列表 [{word, position, level}]');

            // 审核反馈
            $table->text('reject_reason')->nullable()->comment('驳回原因');
            $table->foreignId('current_auditor_id')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('当前审核人');

            $table->timestamp('submitted_at')->nullable()->comment('提交时间');
            $table->timestamp('approved_at')->nullable()->comment('最终通过时间');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('approval_level');
            $table->index('author_id');
            $table->index('current_auditor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
