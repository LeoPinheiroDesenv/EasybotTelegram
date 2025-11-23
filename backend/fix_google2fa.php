<?php

/**
 * Script para verificar e instalar o pacote Google2FA
 */

require __DIR__.'/vendor/autoload.php';

echo "==========================================\n";
echo "Verificando pacote Google2FA...\n";
echo "==========================================\n\n";

// Verificar se a classe existe
if (class_exists('PragmaRX\Google2FA\Google2FA')) {
    echo "✅ Classe Google2FA encontrada!\n";
    
    try {
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        echo "✅ Instância criada com sucesso!\n\n";
        
        // Testar métodos básicos
        $secret = $google2fa->generateSecretKey();
        echo "✅ Método generateSecretKey() funcionando!\n";
        echo "   Secret gerado: " . substr($secret, 0, 20) . "...\n\n";
        
        echo "✅ Pacote Google2FA está funcionando corretamente!\n";
        exit(0);
    } catch (\Exception $e) {
        echo "❌ Erro ao criar instância: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "❌ Classe Google2FA NÃO encontrada!\n\n";
    echo "Execute os seguintes comandos:\n";
    echo "1. docker-compose exec backend composer require pragmarx/google2fa:^9.0\n";
    echo "2. docker-compose exec backend composer dump-autoload -o\n";
    echo "3. Execute este script novamente\n";
    exit(1);
}

