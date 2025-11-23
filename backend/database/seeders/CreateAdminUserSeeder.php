<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar se o usuário admin já existe
        $admin = User::where('email', 'admin@admin.com')->first();
        
        if (!$admin) {
            User::create([
                'name' => 'Administrator',
                'email' => 'admin@admin.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'active' => true,
                'two_factor_enabled' => false,
            ]);
            
            $this->command->info('Admin user created successfully!');
        } else {
            $this->command->info('Admin user already exists.');
        }
    }
}
