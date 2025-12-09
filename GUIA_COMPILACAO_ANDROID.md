# 📱 Guia de Compilação para Android

Este guia explica como compilar sua aplicação React para Android usando **Capacitor** (recomendado) ou transformá-la em **PWA**.

## 🎯 Opção 1: Capacitor (Recomendado)

Capacitor permite transformar sua aplicação web React em um app Android nativo, mantendo todo o código existente.

### Pré-requisitos

1. **Node.js** (já instalado)
2. **Android Studio** com Android SDK
3. **Java JDK 11 ou superior**

### Passo 1: Instalar Capacitor

```bash
cd frontend
npm install @capacitor/core @capacitor/cli
npm install @capacitor/android
```

### Passo 2: Inicializar Capacitor

```bash
npx cap init
```

Quando solicitado:
- **App name**: Bot Telegram (ou o nome desejado)
- **App ID**: com.bottelegram.app (ou seu domínio reverso)
- **Web dir**: build

### Passo 3: Configurar Capacitor

Edite `capacitor.config.json` (será criado automaticamente):

```json
{
  "appId": "com.bottelegram.app",
  "appName": "Bot Telegram",
  "webDir": "build",
  "server": {
    "androidScheme": "https"
  },
  "plugins": {
    "SplashScreen": {
      "launchShowDuration": 2000,
      "backgroundColor": "#9333ea"
    }
  }
}
```

### Passo 4: Build da aplicação React

```bash
npm run build
```

### Passo 5: Adicionar plataforma Android

```bash
npx cap add android
```

### Passo 6: Sincronizar arquivos

```bash
npx cap sync
```

### Passo 7: Abrir no Android Studio

```bash
npx cap open android
```

### Passo 8: Compilar APK/AAB no Android Studio

1. No Android Studio, vá em **Build > Build Bundle(s) / APK(s) > Build APK(s)**
2. Ou **Build > Generate Signed Bundle / APK** para versão de produção
3. O arquivo será gerado em `android/app/build/outputs/apk/`

### Comandos úteis

```bash
# Rebuild e sincronizar após mudanças
npm run build
npx cap sync

# Abrir Android Studio
npx cap open android

# Executar no emulador/dispositivo conectado
npx cap run android
```

---

## 🌐 Opção 2: PWA (Progressive Web App)

Transforma sua aplicação em um PWA que pode ser instalado diretamente do navegador Android.

### Passo 1: Criar manifest.json

Crie `frontend/public/manifest.json`:

```json
{
  "short_name": "Bot Telegram",
  "name": "Bot Telegram - Sistema de Gerenciamento",
  "icons": [
    {
      "src": "favicon.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "favicon.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ],
  "start_url": "/",
  "display": "standalone",
  "theme_color": "#9333ea",
  "background_color": "#ffffff",
  "orientation": "portrait"
}
```

### Passo 2: Atualizar index.html

Adicione ao `<head>` do `frontend/public/index.html`:

```html
<link rel="manifest" href="/manifest.json" />
<meta name="theme-color" content="#9333ea" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
```

### Passo 3: Criar Service Worker (Opcional - para funcionamento offline)

Crie `frontend/public/service-worker.js`:

```javascript
const CACHE_NAME = 'bottelegram-v1';
const urlsToCache = [
  '/',
  '/static/css/main.css',
  '/static/js/main.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => response || fetch(event.request))
  );
});
```

### Passo 4: Registrar Service Worker

Adicione ao final de `frontend/src/index.js`:

```javascript
// Registrar service worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js')
      .then((registration) => {
        console.log('SW registered: ', registration);
      })
      .catch((registrationError) => {
        console.log('SW registration failed: ', registrationError);
      });
  });
}
```

### Passo 5: Build e deploy

```bash
npm run build
```

Depois, faça o deploy do conteúdo da pasta `build` para seu servidor web.

### Instalação no Android

1. Acesse a aplicação no navegador Chrome do Android
2. Toque no menu (3 pontos) > **"Adicionar à tela inicial"**
3. O app será instalado como um ícone na tela inicial

---

## 🔄 Comparação das Opções

| Característica | Capacitor | PWA |
|---------------|-----------|-----|
| **Acesso à Play Store** | ✅ Sim | ❌ Não (mas pode ser instalado) |
| **Recursos Nativos** | ✅ Completo | ⚠️ Limitado |
| **Performance** | ✅ Nativa | ⚠️ Web |
| **Complexidade** | Média | Baixa |
| **Manutenção** | Média | Baixa |
| **Offline** | ✅ Sim | ✅ Sim (com SW) |

---

## 📋 Checklist de Preparação

### Para Capacitor:
- [ ] Instalar Android Studio
- [ ] Configurar Android SDK (API 21+)
- [ ] Instalar Java JDK 11+
- [ ] Configurar variáveis de ambiente ANDROID_HOME
- [ ] Criar conta Google Play Developer (para publicar)

### Para PWA:
- [ ] Servidor HTTPS (obrigatório para PWA)
- [ ] Ícones em diferentes tamanhos (192x192, 512x512)
- [ ] Testar em dispositivos Android reais

---

## 🚀 Próximos Passos

1. **Escolha uma opção** (recomendamos Capacitor para app completo)
2. **Siga os passos** da opção escolhida
3. **Teste** em dispositivos Android reais
4. **Publique** na Google Play Store (se usar Capacitor)

---

## 📚 Recursos Adicionais

- [Documentação Capacitor](https://capacitorjs.com/docs)
- [Guia PWA do Google](https://web.dev/progressive-web-apps/)
- [Android Studio Download](https://developer.android.com/studio)

---

## ⚠️ Notas Importantes

1. **Backend**: Certifique-se de que o backend está acessível via HTTPS em produção
2. **CORS**: Configure CORS corretamente no backend para permitir requisições do app
3. **API URL**: Use variáveis de ambiente para diferentes ambientes (dev/prod)
4. **Segurança**: Implemente autenticação adequada e validação de certificados SSL

---

## 🆘 Troubleshooting

### Erro: "Android SDK not found"
- Configure `ANDROID_HOME` nas variáveis de ambiente
- No Windows: `C:\Users\SeuUsuario\AppData\Local\Android\Sdk`
- No Linux/Mac: `~/Android/Sdk`

### Erro: "Gradle sync failed"
- Abra o projeto no Android Studio e aguarde o sync completo
- Verifique se todas as dependências foram baixadas

### PWA não instala
- Certifique-se de usar HTTPS
- Verifique se o manifest.json está acessível
- Teste no Chrome DevTools > Application > Manifest
