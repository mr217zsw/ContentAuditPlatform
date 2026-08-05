<?php

namespace Database\Seeders;

use App\Models\SensitiveWord;
use Illuminate\Database\Seeder;

class SensitiveWordSeeder extends Seeder
{
    public function run(): void
    {
        $words = [
            ['word' => '赌博', 'level' => 'forbidden'],
            ['word' => '色情', 'level' => 'forbidden'],
            ['word' => '毒品', 'level' => 'forbidden'],
            ['word' => '暴力', 'level' => 'high'],
            ['word' => '诈骗', 'level' => 'high'],
            ['word' => '传销', 'level' => 'high'],
            ['word' => '煽动', 'level' => 'high'],
            ['word' => '谣言', 'level' => 'medium'],
            ['word' => '恶搞', 'level' => 'medium'],
            ['word' => '违规', 'level' => 'low'],
        ];

        foreach ($words as $word) {
            SensitiveWord::create($word);
        }
    }
}
