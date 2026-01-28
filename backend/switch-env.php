<?php

$env = $argv[1] ?? null;

if (!$env) {
    echo "Uso: php switch-env.php [local|production]\n";
    exit(1);
}

$source = __DIR__ . "/.env.{$env}";
$dest = __DIR__ . "/.env";

if (!file_exists($source)) {
    echo "Arquivo de ambiente não encontrado: {$source}\n";
    exit(1);
}

if (copy($source, $dest)) {
    echo "Ambiente alterado para: {$env}\n";
    echo "Arquivo .env atualizado com sucesso.\n";
} else {
    echo "Erro ao copiar arquivo de ambiente.\n";
    exit(1);
}
