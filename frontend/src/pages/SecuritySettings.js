import React, { useState, useEffect, useContext } from 'react';
import Layout from '../components/Layout';
import { AuthContext } from '../contexts/AuthContext';
import authService from '../services/authService';
import useConfirm from '../hooks/useConfirm';
import './SecuritySettings.css';

const SecuritySettings = () => {
  const { confirm, DialogComponent } = useConfirm();
  const { user, refreshUser } = useContext(AuthContext);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [qrCode, setQrCode] = useState(null);
  const [secret, setSecret] = useState(null);
  const [verificationToken, setVerificationToken] = useState('');
  const [showQRCode, setShowQRCode] = useState(false);
  const [is2FAEnabled, setIs2FAEnabled] = useState(user?.two_factor_enabled || false);

  useEffect(() => {
    setIs2FAEnabled(user?.two_factor_enabled || false);
  }, [user]);

  const handleSetup2FA = async () => {
    setLoading(true);
    setError('');
    setSuccess('');
    setVerificationToken('');
    
    try {
      const response = await authService.setup2FA();
      setQrCode(response.qrCode);
      setSecret(response.manualEntryKey);
      setShowQRCode(true);
      setSuccess('Escaneie o QR code com seu aplicativo autenticador e depois digite o código para ativar.');
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao configurar 2FA');
      setShowQRCode(false);
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyAndEnable = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setSuccess('');

    if (verificationToken.length !== 6) {
      setError('O código deve ter 6 dígitos');
      setLoading(false);
      return;
    }

    try {
      await authService.verifyAndEnable2FA(verificationToken);
      setSuccess('2FA ativado com sucesso!');
      setIs2FAEnabled(true);
      setShowQRCode(false);
      setQrCode(null);
      setSecret(null);
      setVerificationToken('');
      
      // Atualiza o usuário no contexto
      await refreshUser();
    } catch (err) {
      setError(err.response?.data?.error || 'Código inválido. Verifique e tente novamente.');
    } finally {
      setLoading(false);
    }
  };

  const handleDisable2FA = async () => {
    const confirmed = await confirm({
      message: 'Tem certeza que deseja desativar o 2FA? Isso reduzirá a segurança da sua conta.',
      type: 'warning',
    });
    
    if (!confirmed) {
      return;
    }

    setLoading(true);
    setError('');
    setSuccess('');

    try {
      await authService.disable2FA();
      setSuccess('2FA desativado com sucesso!');
      setIs2FAEnabled(false);
      
      // Atualiza o usuário no contexto
      await refreshUser();
    } catch (err) {
      setError(err.response?.data?.error || 'Erro ao desativar 2FA');
    } finally {
      setLoading(false);
    }
  };

  const handleCancelSetup = () => {
    setShowQRCode(false);
    setQrCode(null);
    setSecret(null);
    setVerificationToken('');
    setError('');
    setSuccess('');
  };

  return (
    <Layout>
      <DialogComponent />
      <div className="security-settings-container">
        <div className="security-settings-header">
          <h1>Configurações de Segurança</h1>
          <p>Gerencie a autenticação de dois fatores (2FA) da sua conta</p>
        </div>

        <div className="security-settings-content">
          <div className="security-card">
            <div className="security-card-header">
              <h2>Autenticação de Dois Fatores (2FA)</h2>
              <div className={`2fa-status ${is2FAEnabled ? 'enabled' : 'disabled'}`}>
                {is2FAEnabled ? '✓ Ativado' : '✗ Desativado'}
              </div>
            </div>

            <div className="security-card-body">
              <p className="security-description">
                A autenticação de dois fatores adiciona uma camada extra de segurança à sua conta.
                Quando ativada, você precisará fornecer um código do seu aplicativo autenticador além da senha para fazer login.
              </p>

              {error && (
                <div className="alert alert-error">
                  {error}
                </div>
              )}

              {success && (
                <div className="alert alert-success">
                  {success}
                </div>
              )}

              {!is2FAEnabled && !showQRCode && (
                <div className="security-actions">
                  <button
                    className="btn btn-primary"
                    onClick={handleSetup2FA}
                    disabled={loading}
                  >
                    {loading ? 'Configurando...' : 'Ativar 2FA'}
                  </button>
                </div>
              )}

              {showQRCode && (
                <div className="2fa-setup">
                  <h3>Passo 1: Escaneie o QR Code</h3>
                  <p>Use um aplicativo autenticador (Google Authenticator, Microsoft Authenticator, Authy, etc.) para escanear este código:</p>
                  
                  {qrCode && (
                    <div className="qr-code-container">
                      <img src={qrCode} alt="QR Code 2FA" className="qr-code-image" />
                    </div>
                  )}

                  {secret && (
                    <div className="manual-entry">
                      <p><strong>Ou insira manualmente:</strong></p>
                      <code className="secret-key">{secret}</code>
                    </div>
                  )}

                  <h3 style={{ marginTop: '30px' }}>Passo 2: Verifique o Código</h3>
                  <p>Após escanear o QR code, digite o código de 6 dígitos gerado pelo aplicativo:</p>

                  <form onSubmit={handleVerifyAndEnable} className="verify-form">
                    <div className="form-group">
                      <input
                        type="text"
                        className="form-control"
                        value={verificationToken}
                        onChange={(e) => setVerificationToken(e.target.value.replace(/\D/g, '').slice(0, 6))}
                        placeholder="000000"
                        maxLength="6"
                        required
                        disabled={loading}
                        autoFocus
                      />
                    </div>
                    <div className="form-actions">
                      <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={loading || verificationToken.length !== 6}
                      >
                        {loading ? 'Verificando...' : 'Ativar 2FA'}
                      </button>
                      <button
                        type="button"
                        className="btn btn-secondary"
                        onClick={handleCancelSetup}
                        disabled={loading}
                      >
                        Cancelar
                      </button>
                    </div>
                  </form>
                </div>
              )}

              {is2FAEnabled && !showQRCode && (
                <div className="security-actions">
                  <button
                    className="btn btn-danger"
                    onClick={handleDisable2FA}
                    disabled={loading}
                  >
                    {loading ? 'Desativando...' : 'Desativar 2FA'}
                  </button>
                </div>
              )}
            </div>
          </div>

          <div className="security-info">
            <h3>Como usar o 2FA</h3>
            <ul>
              <li>Instale um aplicativo autenticador no seu celular (Google Authenticator, Microsoft Authenticator, Authy, etc.)</li>
              <li>Escaneie o QR code ou insira a chave manualmente</li>
              <li>Digite o código de 6 dígitos gerado pelo aplicativo para ativar</li>
              <li>Nas próximas vezes que fizer login, você precisará fornecer o código do aplicativo</li>
            </ul>

            <h3>Dicas de Segurança</h3>
            <ul>
              <li>Guarde a chave secreta em local seguro caso precise reconfigurar</li>
              <li>Você pode escanear o mesmo QR code em múltiplos dispositivos</li>
              <li>Os códigos mudam a cada 30 segundos</li>
              <li>Se perder acesso ao celular, entre em contato com o administrador</li>
            </ul>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default SecuritySettings;

