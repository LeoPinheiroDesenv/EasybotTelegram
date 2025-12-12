# Correção: Renovação e Envio de Links de Grupo

## Problemas Identificados

### 1. Comando de Expiração Não Renovava Links
- **Problema**: O comando `check:group-link-expiration` apenas notificava quando o link expirava, mas não renovava o link nem enviava o novo link ao usuário.
- **Impacto**: Usuários recebiam notificação de expiração, mas não recebiam o novo link.

### 2. Links Não Enviados em Renovações
- **Problema**: Quando um usuário fazia um novo pagamento (renovação), se o status anterior já era aprovado, o sistema não enviava o link novamente.
- **Impacto**: Usuários que renovavam o plano não recebiam o link do grupo.

## Correções Implementadas

### 1. Comando de Expiração (`CheckGroupLinkExpirationCommand`)

#### Melhorias:
- ✅ **Renovação Automática de Links**: Quando detecta link expirado, tenta renovar automaticamente se a transação ainda estiver válida.
- ✅ **Envio de Novo Link**: Se o link for renovado com sucesso, envia o novo link ao usuário.
- ✅ **Verificação de Pagamentos Válidos**: Verifica se o usuário tem pagamento válido mais recente antes de processar.
- ✅ **Mensagens Diferenciadas**: 
  - Se link foi renovado: envia mensagem com novo link
  - Se link não foi renovado: envia mensagem informando que precisa fazer novo pagamento

#### Fluxo:
1. Detecta link expirado
2. Verifica se usuário tem pagamento válido mais recente
3. Se transação ainda está válida, tenta renovar o link
4. Se renovação bem-sucedida, envia novo link ao usuário
5. Se renovação falhou, notifica sobre expiração

### 2. Processamento de Aprovação de Pagamento (`PaymentService::processPaymentApproval`)

#### Melhorias:
- ✅ **Sempre Envia Link em Novas Transações**: Transações com status anterior 'pending' sempre enviam notificação com link.
- ✅ **Envia Link em Renovações**: Mesmo que o status anterior já fosse aprovado, envia link se não foi notificado recentemente (últimos 2 minutos).
- ✅ **Prevenção de Duplicatas**: Evita notificações duplicadas em caso de webhooks repetidos (verifica últimos 2 minutos).
- ✅ **Logs Detalhados**: Logs claros indicando se é nova transação ou renovação.

#### Lógica:
- **Nova Transação** (`oldStatus = 'pending'`): Sempre notifica e envia link
- **Renovação** (`oldStatus = 'approved'` e notificação há mais de 2 minutos): Notifica e envia link
- **Webhook Duplicado** (`oldStatus = 'approved'` e notificação há menos de 2 minutos): Não notifica

### 3. Método `findGroupInviteLink` Tornado Público

- ✅ Método agora é `public` para permitir uso no comando de expiração
- ✅ Permite renovação de links quando necessário

## Como Funciona Agora

### Cenário 1: Link Expira
1. Comando `check:group-link-expiration` detecta link expirado
2. Verifica se transação ainda está válida
3. Se válida, renova o link automaticamente
4. Envia novo link ao usuário via Telegram
5. Atualiza metadata com novo link e nova data de expiração

### Cenário 2: Usuário Faz Novo Pagamento
1. Nova transação é criada com status 'pending'
2. Quando aprovada, `processPaymentApproval` é chamado
3. Sistema detecta que é nova transação (`oldStatus = 'pending'`)
4. Sempre envia notificação com link do grupo
5. Link é criado com expiração baseada no ciclo do plano

### Cenário 3: Usuário Renova Plano
1. Nova transação é criada (ou transação antiga é atualizada)
2. Quando aprovada, sistema verifica status anterior
3. Se status anterior era 'approved' mas notificação foi há mais de 2 minutos, envia link
4. Link é renovado e enviado ao usuário

## Arquivos Modificados

1. **`backend/app/Console/Commands/CheckGroupLinkExpirationCommand.php`**
   - Adicionada lógica de renovação automática de links
   - Adicionada verificação de pagamentos válidos mais recentes
   - Melhoradas mensagens enviadas ao usuário

2. **`backend/app/Services/PaymentService.php`**
   - Corrigida lógica de notificação em renovações
   - Método `findGroupInviteLink` tornado público
   - Adicionado import de `Carbon\Carbon`
   - Melhorada lógica de prevenção de duplicatas

## Como Testar

### Teste 1: Renovação Automática de Link Expirado
```bash
# Executa comando de verificação
php artisan check:group-link-expiration

# Verifica logs para ver se links foram renovados
tail -f storage/logs/laravel.log | grep "Link de grupo renovado"
```

### Teste 2: Novo Pagamento
1. Cria um novo pagamento PIX
2. Aprova o pagamento
3. Verifica se link foi enviado ao usuário
4. Verifica logs: `tail -f storage/logs/laravel.log | grep "Nova transação aprovada"`

### Teste 3: Renovação de Plano
1. Usuário faz novo pagamento (renovação)
2. Verifica se link foi enviado mesmo sendo renovação
3. Verifica logs: `tail -f storage/logs/laravel.log | grep "possível renovação"`

## Logs Importantes

### Quando Link é Renovado:
```
Link de grupo renovado automaticamente
```

### Quando Link é Enviado em Nova Transação:
```
Nova transação aprovada - enviando notificação com link do grupo
```

### Quando Link é Enviado em Renovação:
```
Pagamento já estava aprovado mas notificação foi há mais de 2 minutos - enviando novamente (possível renovação)
```

## Próximos Passos

1. ✅ Renovação automática implementada
2. ✅ Envio de links em renovações corrigido
3. ⏳ **Monitorar logs** para verificar se está funcionando corretamente
4. ⏳ **Testar com usuários reais** para validar o fluxo completo
