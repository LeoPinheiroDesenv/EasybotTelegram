<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de Senha</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #1e7e34 0%, #22c55e 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
        <h1 style="color: white; margin: 0;">Recuperação de Senha</h1>
    </div>
    
    <div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
        <p>Olá,</p>
        
        <p>Você solicitou a recuperação de senha para sua conta. Clique no botão abaixo para redefinir sua senha:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" 
               style="display: inline-block; background: linear-gradient(135deg, #1e7e34 0%, #22c55e 100%); 
                      color: white; padding: 14px 32px; text-decoration: none; 
                      border-radius: 8px; font-weight: 600;">
                Redefinir Senha
            </a>
        </div>
        
        <p style="font-size: 14px; color: #6b7280;">
            Ou copie e cole este link no seu navegador:<br>
            <a href="{{ $resetUrl }}" style="color: #22c55e; word-break: break-all;">{{ $resetUrl }}</a>
        </p>
        
        <p style="font-size: 14px; color: #6b7280; margin-top: 30px;">
            <strong>Importante:</strong> Este link expira em 1 hora. Se você não solicitou esta recuperação, ignore este email.
        </p>
        
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
        
        <p style="font-size: 12px; color: #9ca3af; text-align: center; margin: 0;">
            Este é um email automático, por favor não responda.
        </p>
    </div>
</body>
</html>

