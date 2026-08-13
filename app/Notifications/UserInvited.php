<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvited extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Você foi convidado — SDV Access Santa Rita')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Um administrador criou uma conta para você no SDV Access Santa Rita.')
            ->line('Para começar a usar o sistema, defina sua própria senha de acesso.')
            ->action('Definir minha senha', $url)
            ->line('Este link expira em '.config('auth.passwords.users.expire').' minutos.')
            ->line('Se você não esperava este convite, ignore este e-mail.');
    }
}
