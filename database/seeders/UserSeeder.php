<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => '管理员',
                'email' => 'admin@audit.local',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'name' => '编辑张三',
                'email' => 'editor@audit.local',
                'password' => Hash::make('editor123'),
                'role' => 'editor',
                'is_active' => true,
            ],
            [
                'name' => '编辑李四',
                'email' => 'editor2@audit.local',
                'password' => Hash::make('editor123'),
                'role' => 'editor',
                'is_active' => true,
            ],
            [
                'name' => '主管王五',
                'email' => 'supervisor@audit.local',
                'password' => Hash::make('supervisor123'),
                'role' => 'supervisor',
                'is_active' => true,
            ],
            [
                'name' => '终审赵六',
                'email' => 'final@audit.local',
                'password' => Hash::make('final123'),
                'role' => 'final_approver',
                'is_active' => true,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
