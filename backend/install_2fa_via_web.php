<?php
/**
 * Script de Instalação do Google2FA via Web
 * 
 * INSTRUÇÕES:
 * 1. Faça upload deste arquivo para o diretório raiz do backend via FTP
 * 2. Acesse via navegador: https://api.easypagamentos.com/install_2fa_via_web.php
 * 3. Siga as instruções na tela
 * 4. APÓS A INSTALAÇÃO, DELETE ESTE ARQUIVO POR SEGURANÇA!
 * 
 * ATENÇÃO: Este script deve ser removido após o uso por questões de segurança!
 */

// Verifica se está sendo executado via web
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_HOST'])) {
    die('Este script deve ser executado via navegador ou linha de comando.');
}

// Configurações de segurança - ALTERE ESTA SENHA!
$SECURITY_PASSWORD = 'AltereEstaSenha123!'; // MUDE ESTA SENHA ANTES DE USAR!

// Verifica senha de segurança
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['password']) || $_GET['password'] !== $SECURITY_PASSWORD) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Instalação Google2FA - Segurança</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
                input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
                button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
                button:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <h1>🔒 Instalação Google2FA</h1>
            <div class="info">
                <strong>⚠️ IMPORTANTE:</strong> Este script deve ser removido após o uso!
            </div>
            <form method="GET">
                <label>Senha de Segurança:</label>
                <input type="password" name="password" placeholder="Digite a senha" required>
                <button type="submit">Continuar</button>
            </form>
            <div class="error">
                <strong>Nota:</strong> Altere a variável $SECURITY_PASSWORD no arquivo antes de usar!
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Função para executar comandos
function executeCommand($command) {
    $output = [];
    $return_var = 0;
    
    if (php_sapi_name() === 'cli') {
        exec($command . ' 2>&1', $output, $return_var);
    } else {
        // Via web, tenta usar shell_exec ou exec
        if (function_exists('shell_exec')) {
            $output = shell_exec($command . ' 2>&1');
            $output = $output ? explode("\n", trim($output)) : [];
        } elseif (function_exists('exec')) {
            exec($command . ' 2>&1', $output, $return_var);
        } else {
            return ['error' => 'Funções shell_exec e exec não estão disponíveis'];
        }
    }
    
    return [
        'output' => $output,
        'return_code' => $return_var,
        'success' => $return_var === 0
    ];
}

// Função para verificar se o Composer está disponível
function checkComposer() {
    $result = executeCommand('composer --version');
    return $result['success'];
}

// Função para instalar pacote via Composer
function installPackage($package) {
    $command = "composer require {$package} --no-interaction --no-plugins 2>&1";
    return executeCommand($command);
}

// Função para verificar se pacote está instalado
function isPackageInstalled($package) {
    $result = executeCommand("composer show {$package} 2>&1");
    return $result['success'];
}

// HTML Header
if (php_sapi_name() !== 'cli') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Instalação Google2FA</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h1 { color: #333; }
            .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #28a745; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #dc3545; }
            .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #17a2b8; }
            .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
            .step h3 { margin-top: 0; color: #007bff; }
            pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
            .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 10px 0; }
            .btn:hover { background: #0056b3; }
            .btn-danger { background: #dc3545; }
            .btn-danger:hover { background: #c82333; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔐 Instalação do Google2FA</h1>
    <?php
}

$steps = [];
$allSuccess = true;

// Passo 1: Verificar Composer
$steps[] = ['title' => 'Verificando Composer', 'status' => 'checking'];
if (php_sapi_name() !== 'cli') {
    echo '<div class="step"><h3>Passo 1: Verificando Composer</h3>';
}

if (checkComposer()) {
    $steps[count($steps)-1]['status'] = 'success';
    $steps[count($steps)-1]['message'] = 'Composer encontrado!';
    if (php_sapi_name() !== 'cli') {
        echo '<div class="success">✅ Composer encontrado e funcionando!</div>';
    } else {
        echo "✅ Composer encontrado!\n";
    }
} else {
    $steps[count($steps)-1]['status'] = 'error';
    $steps[count($steps)-1]['message'] = 'Composer não encontrado!';
    $allSuccess = false;
    if (php_sapi_name() !== 'cli') {
        echo '<div class="error">❌ Composer não encontrado! Instale o Composer primeiro.</div>';
    } else {
        echo "❌ Composer não encontrado!\n";
    }
}

if (php_sapi_name() !== 'cli') {
    echo '</div>';
}

// Passo 2: Instalar Google2FA
if ($allSuccess) {
    $steps[] = ['title' => 'Instalando Google2FA', 'status' => 'checking'];
    if (php_sapi_name() !== 'cli') {
        echo '<div class="step"><h3>Passo 2: Instalando pragmarx/google2fa</h3>';
    }
    
    if (isPackageInstalled('pragmarx/google2fa')) {
        $steps[count($steps)-1]['status'] = 'success';
        $steps[count($steps)-1]['message'] = 'Google2FA já está instalado!';
        if (php_sapi_name() !== 'cli') {
            echo '<div class="info">ℹ️ Google2FA já está instalado!</div>';
        } else {
            echo "ℹ️ Google2FA já está instalado!\n";
        }
    } else {
        if (php_sapi_name() !== 'cli') {
            echo '<div class="info">📦 Instalando pacote... Isso pode levar alguns minutos.</div>';
        } else {
            echo "📦 Instalando pacote...\n";
        }
        
        $result = installPackage('pragmarx/google2fa:^9.0');
        
        if ($result['success']) {
            $steps[count($steps)-1]['status'] = 'success';
            $steps[count($steps)-1]['message'] = 'Google2FA instalado com sucesso!';
            if (php_sapi_name() !== 'cli') {
                echo '<div class="success">✅ Google2FA instalado com sucesso!</div>';
            } else {
                echo "✅ Google2FA instalado com sucesso!\n";
            }
        } else {
            $steps[count($steps)-1]['status'] = 'error';
            $steps[count($steps)-1]['message'] = 'Erro ao instalar Google2FA';
            $allSuccess = false;
            if (php_sapi_name() !== 'cli') {
                echo '<div class="error">❌ Erro ao instalar Google2FA:</div>';
                echo '<pre>' . htmlspecialchars(implode("\n", $result['output'])) . '</pre>';
            } else {
                echo "❌ Erro ao instalar Google2FA:\n";
                echo implode("\n", $result['output']) . "\n";
            }
        }
    }
    
    if (php_sapi_name() !== 'cli') {
        echo '</div>';
    }
}

// Passo 3: Instalar SimpleSoftwareIO QR Code
if ($allSuccess) {
    $steps[] = ['title' => 'Instalando SimpleSoftwareIO QR Code', 'status' => 'checking'];
    if (php_sapi_name() !== 'cli') {
        echo '<div class="step"><h3>Passo 3: Instalando simplesoftwareio/simple-qrcode</h3>';
    }
    
    if (isPackageInstalled('simplesoftwareio/simple-qrcode')) {
        $steps[count($steps)-1]['status'] = 'success';
        $steps[count($steps)-1]['message'] = 'SimpleSoftwareIO QR Code já está instalado!';
        if (php_sapi_name() !== 'cli') {
            echo '<div class="info">ℹ️ SimpleSoftwareIO QR Code já está instalado!</div>';
        } else {
            echo "ℹ️ SimpleSoftwareIO QR Code já está instalado!\n";
        }
    } else {
        if (php_sapi_name() !== 'cli') {
            echo '<div class="info">📦 Instalando pacote... Isso pode levar alguns minutos.</div>';
        } else {
            echo "📦 Instalando pacote...\n";
        }
        
        $result = installPackage('simplesoftwareio/simple-qrcode:^4.2');
        
        if ($result['success']) {
            $steps[count($steps)-1]['status'] = 'success';
            $steps[count($steps)-1]['message'] = 'SimpleSoftwareIO QR Code instalado com sucesso!';
            if (php_sapi_name() !== 'cli') {
                echo '<div class="success">✅ SimpleSoftwareIO QR Code instalado com sucesso!</div>';
            } else {
                echo "✅ SimpleSoftwareIO QR Code instalado com sucesso!\n";
            }
        } else {
            $steps[count($steps)-1]['status'] = 'error';
            $steps[count($steps)-1]['message'] = 'Erro ao instalar SimpleSoftwareIO QR Code';
            $allSuccess = false;
            if (php_sapi_name() !== 'cli') {
                echo '<div class="error">❌ Erro ao instalar SimpleSoftwareIO QR Code:</div>';
                echo '<pre>' . htmlspecialchars(implode("\n", $result['output'])) . '</pre>';
            } else {
                echo "❌ Erro ao instalar SimpleSoftwareIO QR Code:\n";
                echo implode("\n", $result['output']) . "\n";
            }
        }
    }
    
    if (php_sapi_name() !== 'cli') {
        echo '</div>';
    }
}

// Passo 4: Otimizar autoload
if ($allSuccess) {
    $steps[] = ['title' => 'Otimizando autoload', 'status' => 'checking'];
    if (php_sapi_name() !== 'cli') {
        echo '<div class="step"><h3>Passo 4: Otimizando autoload</h3>';
    }
    
    $result = executeCommand('composer dump-autoload --optimize --no-interaction');
    
    if ($result['success']) {
        $steps[count($steps)-1]['status'] = 'success';
        $steps[count($steps)-1]['message'] = 'Autoload otimizado!';
        if (php_sapi_name() !== 'cli') {
            echo '<div class="success">✅ Autoload otimizado com sucesso!</div>';
        } else {
            echo "✅ Autoload otimizado!\n";
        }
    } else {
        $steps[count($steps)-1]['status'] = 'warning';
        $steps[count($steps)-1]['message'] = 'Aviso ao otimizar autoload';
        if (php_sapi_name() !== 'cli') {
            echo '<div class="warning">⚠️ Aviso ao otimizar autoload (não crítico)</div>';
        } else {
            echo "⚠️ Aviso ao otimizar autoload\n";
        }
    }
    
    if (php_sapi_name() !== 'cli') {
        echo '</div>';
    }
}

// Resumo final
if (php_sapi_name() !== 'cli') {
    echo '<div class="step">';
    echo '<h3>📋 Resumo da Instalação</h3>';
    
    if ($allSuccess) {
        echo '<div class="success">';
        echo '<h2>✅ Instalação Concluída com Sucesso!</h2>';
        echo '<p>Os pacotes necessários para o 2FA foram instalados corretamente.</p>';
        echo '</div>';
        
        echo '<div class="warning">';
        echo '<h3>⚠️ IMPORTANTE - AÇÃO NECESSÁRIA:</h3>';
        echo '<p><strong>DELETE ESTE ARQUIVO IMEDIATAMENTE POR SEGURANÇA!</strong></p>';
        echo '<p>Este script não deve permanecer no servidor após a instalação.</p>';
        echo '</div>';
        
        echo '<div class="info">';
        echo '<h3>📝 Próximos Passos:</h3>';
        echo '<ol>';
        echo '<li>Teste o endpoint de 2FA: <code>GET /api/auth/2fa/setup</code></li>';
        echo '<li>Verifique se não há erros nos logs</li>';
        echo '<li><strong>DELETE este arquivo (install_2fa_via_web.php) via FTP</strong></li>';
        echo '</ol>';
        echo '</div>';
    } else {
        echo '<div class="error">';
        echo '<h2>❌ Erro na Instalação</h2>';
        echo '<p>Alguns pacotes não puderam ser instalados. Verifique os erros acima.</p>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
} else {
    if ($allSuccess) {
        echo "\n✅ Instalação concluída com sucesso!\n";
        echo "⚠️  IMPORTANTE: Delete este arquivo após o uso!\n";
    } else {
        echo "\n❌ Erro na instalação. Verifique os erros acima.\n";
    }
}

