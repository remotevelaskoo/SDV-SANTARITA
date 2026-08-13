<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (AuditObserver::observedModels() as $model) {
            $model::observe(AuditObserver::class);
        }

        $resetUrl = fn (User $notifiable, string $token) => route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        ResetPassword::createUrlUsing($resetUrl);

        ResetPassword::toMailUsing(fn (User $notifiable, string $token) => (new MailMessage)
            ->subject('Redefinição de senha — SDV Access Santa Rita')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta no SDV Access Santa Rita.')
            ->action('Redefinir senha', $resetUrl($notifiable, $token))
            ->line('Este link expira em '.config('auth.passwords.users.expire').' minutos.')
            ->line('Se você não solicitou esta alteração, nenhuma ação é necessária.'));
    }
}
