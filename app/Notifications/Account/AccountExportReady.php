<?php

namespace App\Notifications\Account;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AccountExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail message with a short-lived signed download link.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'account.export.download',
            now()->addMinutes(60),
            ['export' => $notifiable->ulid],
        );

        return (new MailMessage)
            ->subject(__('Your data export is ready'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('The personal data export you requested is ready to download.'))
            ->line(__('The download link expires in 60 minutes.'))
            ->action(__('Download export'), $url);
    }
}
