<?php

/**
 * Script para criar/atualizar usuário admin
 * EasyBot Telegram - Admin User Creation/Update
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "==========================================\n";
echo "Criando/Atualizando usuário admin...\n";
echo "==========================================\n\n";

try {
    $admin = User::where('email', 'admin@admin.com')->first();

    if (!$admin) {
        echo "📝 Usuário admin não encontrado. Criando...\n";
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@admin.com',
            'password' => 'admin123', // O mutator setPasswordAttribute vai hashear automaticamente
            'role' => 'admin',
            'active' => true,
            'two_factor_enabled' => false,
        ]);
        echo "✅ Usuário admin criado com sucesso!\n\n";
    } else {
        echo "📝 Usuário admin encontrado. Atualizando...\n";
        
        // Atualizar senha e informações (o mutator setPasswordAttribute vai hashear automaticamente)
        $admin->name = 'Administrator';
        $admin->password = 'admin123';
        $admin->role = 'admin';
        $admin->active = true;
        $admin->two_factor_enabled = false;
        $admin->save();
        
        echo "✅ Usuário admin atualizado com sucesso!\n\n";
    }

    // Verificar se a senha está correta
    if (Hash::check('admin123', $admin->password)) {
        echo "✓ Verificação de senha: SUCESSO\n";
        echo "✓ Senha está usando algoritmo Bcrypt\n\n";
    } else {
        echo "✗ Verificação de senha: FALHOU\n";
        echo "⚠️  A senha pode não estar correta!\n\n";
        exit(1);
    }

    // Exibir informações do usuário
    echo "==========================================\n";
    echo "Credenciais do Admin:\n";
    echo "==========================================\n";
    echo "ID:        " . $admin->id . "\n";
    echo "Nome:      " . $admin->name . "\n";
    echo "Email:     " . $admin->email . "\n";
    echo "Senha:     admin123\n";
    echo "Role:      " . $admin->role . "\n";
    echo "Ativo:     " . ($admin->active ? 'Sim' : 'Não') . "\n";
    echo "2FA:       " . ($admin->two_factor_enabled ? 'Ativado' : 'Desativado') . "\n";
    echo "Criado em: " . $admin->created_at . "\n";
    echo "==========================================\n\n";
    
    echo "⚠️  IMPORTANTE: Altere a senha após o primeiro login!\n";
    echo "⚠️  IMPORTANTE: Ative o 2FA para maior segurança!\n\n";
    
    exit(0);
    
} catch (\Exception $e) {
    echo "❌ Erro ao criar/atualizar usuário admin:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

