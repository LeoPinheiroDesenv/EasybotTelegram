<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Verificar se o usuário admin já existe
$admin = User::where('email', 'admin@admin.com')->first();

if (!$admin) {
    try {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'active' => true,
            'two_factor_enabled' => false,
        ]);
        
        echo "Admin user created successfully!\n";
        echo "Email: admin@admin.com\n";
        echo "Password: admin123\n";
    } catch (\Exception $e) {
        echo "Error creating admin user: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "Admin user already exists.\n";
    echo "Email: " . $admin->email . "\n";
    echo "Role: " . $admin->role . "\n";
    echo "Active: " . ($admin->active ? 'Yes' : 'No') . "\n";
    
    // Atualizar senha caso necessário (sempre re-hashear para garantir Bcrypt)
    $admin->password = Hash::make('admin123');
    $admin->role = 'admin';
    $admin->active = true;
    $admin->two_factor_enabled = false;
    $admin->save();
    echo "Password updated to: admin123 (using Bcrypt)\n";
}

