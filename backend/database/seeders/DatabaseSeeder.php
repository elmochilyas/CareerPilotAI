<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'full_name' => 'Test Candidate',
            'email' => 'candidate@careerpilot.ai',
        ]);

        User::factory()->admin()->create([
            'full_name' => 'Admin User',
            'email' => 'admin@careerpilot.ai',
        ]);
    }
}
