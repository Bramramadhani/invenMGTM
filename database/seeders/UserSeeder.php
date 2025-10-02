<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Cek jika user sudah ada
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'department' => 'Administrator',
            ]
        );

        // Cek dan buat role jika belum ada
        $role = Role::firstOrCreate(['name' => 'Super Admin']);

        // Berikan role ke user
        $user->assignRole($role);
    }
}
