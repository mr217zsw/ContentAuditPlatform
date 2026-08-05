<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensitive_words', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100)->unique()->comment('敏感词');
            $table->enum('level', ['low', 'medium', 'high', 'forbidden'])
                ->default('medium')
                ->comment('low=低风险, medium=中风险, high=高风险, forbidden=禁止');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('level');
            $table->index('is_active');
        });

        Schema::create('sensitive_word_hits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('sensitive_word_id')->constrained('sensitive_words')->cascadeOnDelete();
            $table->string('word', 100)->comment('命中的词');
            $table->integer('position')->nullable()->comment('命中位置（字符偏移）');
            $table->timestamps();

            $table->index('article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_word_hits');
        Schema::dropIfExists('sensitive_words');
    }
};
