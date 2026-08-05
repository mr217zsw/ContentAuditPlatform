<?php

namespace Tests\Feature;

use App\Models\SensitiveWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensitiveWordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SensitiveWord::create(['word' => '赌博', 'level' => 'forbidden']);
        SensitiveWord::create(['word' => '暴力', 'level' => 'high']);
        SensitiveWord::create(['word' => '谣言', 'level' => 'medium']);
    }

    public function test_detect_forbidden_word_in_text(): void
    {
        $service = app(\App\Services\SensitiveWordService::class);
        $hits = $service->detect('这是包含赌博的内容');

        $this->assertNotEmpty($hits);
        $this->assertEquals('赌博', $hits[0]['word']);
        $this->assertEquals('forbidden', $hits[0]['level']);
    }

    public function test_detect_multiple_hits(): void
    {
        $service = app(\App\Services\SensitiveWordService::class);
        $hits = $service->detect('涉及赌博和暴力的内容');

        $this->assertGreaterThanOrEqual(2, count($hits));
    }

    public function test_clean_text_returns_empty(): void
    {
        $service = app(\App\Services\SensitiveWordService::class);
        $hits = $service->detect('这是正常的内容');

        $this->assertEmpty($hits);
    }

    public function test_has_forbidden_words(): void
    {
        $service = app(\App\Services\SensitiveWordService::class);

        $this->assertTrue($service->hasForbiddenWords('包含赌博'));
        $this->assertFalse($service->hasForbiddenWords('包含暴力'));
    }
}
