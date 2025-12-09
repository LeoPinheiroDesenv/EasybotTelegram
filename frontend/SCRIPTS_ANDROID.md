# 🚀 Scripts Rápidos para Android

## Instalação Rápida do Capacitor

Execute estes comandos na ordem:

```bash
cd frontend

# 1. Instalar Capacitor
npm install @capacitor/core @capacitor/cli @capacitor/android

# 2. Inicializar (se ainda não foi feito)
npx cap init

# 3. Build da aplicação
npm run build

# 4. Adicionar plataforma Android
npx cap add android

# 5. Sincronizar
npx cap sync

# 6. Abrir no Android Studio
npx cap open android
```

## Scripts NPM Úteis

Adicione ao `package.json` na seção `scripts`:

```json
{
  "scripts": {
    "build:android": "npm run build && npx cap sync android",
    "open:android": "npx cap open android",
    "run:android": "npx cap run android",
    "sync:android": "npx cap sync android"
  }
}
```

Depois use:
```bash
npm run build:android  # Build e sincroniza
npm run open:android   # Abre Android Studio
npm run run:android    # Executa no dispositivo/emulador
```

## Configuração Rápida do Ambiente Android

### Windows:
```powershell
# Adicionar ao PATH do sistema:
# C:\Users\SeuUsuario\AppData\Local\Android\Sdk\platform-tools
# C:\Users\SeuUsuario\AppData\Local\Android\Sdk\tools

# Variável de ambiente:
ANDROID_HOME=C:\Users\SeuUsuario\AppData\Local\Android\Sdk
```

### Linux/Mac:
```bash
# Adicionar ao ~/.bashrc ou ~/.zshrc:
export ANDROID_HOME=$HOME/Android/Sdk
export PATH=$PATH:$ANDROID_HOME/platform-tools
export PATH=$PATH:$ANDROID_HOME/tools

# Recarregar:
source ~/.bashrc  # ou source ~/.zshrc
```

## Verificar Instalação

```bash
# Verificar Java
java -version

# Verificar Android SDK
echo $ANDROID_HOME  # Linux/Mac
echo %ANDROID_HOME%  # Windows

# Verificar Capacitor
npx cap doctor
```

## Troubleshooting Rápido

### Erro: "Command not found: cap"
```bash
npm install -g @capacitor/cli
```

### Erro: "Android SDK not found"
1. Instale Android Studio
2. Configure ANDROID_HOME
3. Execute: `npx cap doctor`

### Erro: "Gradle sync failed"
1. Abra Android Studio
2. Aguarde o sync completo
3. Se falhar, vá em File > Invalidate Caches / Restart

### Build falha
```bash
# Limpar cache e rebuild
cd android
./gradlew clean
cd ..
npm run build
npx cap sync
```
