# Como Recompilar o Frontend para Produção

## Problema

O frontend em produção está usando URLs HTTP, causando erro de Mixed Content quando acessado via HTTPS.

## Solução

Recompilar o frontend com as URLs HTTPS atualizadas.

## Opção 1: Usando o Script Automatizado

```bash
cd frontend
./rebuild_production.sh
```

## Opção 2: Manualmente

```bash
cd frontend

# Limpar build anterior
rm -rf build

# Instalar dependências (se necessário)
npm install

# Compilar usando o arquivo .env
npm run build
```

## Opção 3: Configurando a URL da API

A URL da API deve ser configurada no arquivo `.env` na raiz do frontend:

```bash
cd frontend
echo "REACT_APP_API_URL=http://0.0.0.0:8000/api" > .env
```

Ou crie manualmente o arquivo `.env` com o conteúdo:

```
REACT_APP_API_URL=http://0.0.0.0:8000/api
```

**IMPORTANTE:** O arquivo `.env` é o único lugar onde a URL do backend deve ser configurada. Todos os serviços usam essa variável de ambiente.

Depois compile normalmente:

```bash
npm run build
```

## Verificação

Após compilar, verifique se a URL configurada está no build:

```bash
grep -r "REACT_APP_API_URL" build/ || grep -r "0.0.0.0:8000" build/
```

Deve retornar várias ocorrências com a URL configurada no `.env`.

## Deploy

Após compilar, faça o deploy dos arquivos da pasta `build/` para o servidor de produção.

## Nota

- O arquivo `setupProxy.js` é apenas para desenvolvimento local e não afeta a produção
- **A URL do backend deve ser configurada APENAS no arquivo `.env` na raiz do frontend**
- Todos os serviços (`api.js`, `authService.js`, `botService.js`, etc.) usam a instância centralizada do axios de `api.js`
- O arquivo `api.js` valida se `REACT_APP_API_URL` está definida e lança um erro se não estiver

