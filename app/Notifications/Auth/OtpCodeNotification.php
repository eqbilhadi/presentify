<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Verifikasi Presentify')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Gunakan kode berikut untuk masuk. Kode berlaku selama 5 menit.')
            ->line('**' . $this->code . '**')
            ->line('Jangan bagikan kode ini kepada siapa pun.')
            ->salutation('Tim Presentify');
    }
}
