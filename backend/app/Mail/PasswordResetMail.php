<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(string $resetUrl)
    {
        $this->resetUrl = $resetUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        try {
            $fromAddress = config('mail.from.address', env('MAIL_FROM_ADDRESS', 'noreply@easypagamentos.com'));
            $fromName = config('mail.from.name', env('MAIL_FROM_NAME', 'EasyBot Telegram'));
            
            return $this->from($fromAddress, $fromName)
                        ->subject('Recuperação de Senha - ' . config('app.name'))
                        ->view('emails.password-reset')
                        ->with([
                            'resetUrl' => $this->resetUrl,
                        ]);
        } catch (\Exception $e) {
            \Log::error('Error building password reset email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

