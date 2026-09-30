<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    private const array DEFAULT_USERS = [
        'Hind Biswas' => 'hindsbhk@gmail.com',
        'Prodip Hore' => 'prodipcse21@gmail.com ',
        'Rownak Islam Nabil' => 'rin@test.com',
        'Md. Shamim' => 'ms@test.com',
        'Tanmay Sharma' => 'ts@test.com',
        'Rafy' => 'r@test.com',
        'Tanvir Bhai' => 'tb@test.com',
        'Atiqur Rahman' => 'at@test.com',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::DEFAULT_USERS as $name => $email) {
            User::factory()->create([
                'name' => $name,
                'email' => $email,
            ]);
        }
    }
}
