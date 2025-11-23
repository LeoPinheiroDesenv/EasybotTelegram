#!/bin/bash

# =====================================================
# Script Shell para criação do usuário admin
# EasyBot Telegram - Admin User Creation Script
# =====================================================

echo "=========================================="
echo "Criando usuário admin..."
echo "=========================================="
echo ""

# Verificar se o container está rodando
if ! docker-compose ps backend | grep -q "Up"; then
    echo "❌ Erro: Container do backend não está rodando!"
    echo "Execute: docker-compose up -d"
    exit 1
fi

# Executar script PHP para criar/atualizar admin
echo "📝 Executando script PHP..."
docker-compose exec backend php fix_admin_password.php

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Usuário admin criado/atualizado com sucesso!"
    echo ""
    echo "Credenciais:"
    echo "  Email: admin@admin.com"
    echo "  Senha: admin123"
    echo ""
    echo "⚠️  IMPORTANTE: Altere a senha após o primeiro login!"
else
    echo ""
    echo "❌ Erro ao criar usuário admin!"
    exit 1
fi

