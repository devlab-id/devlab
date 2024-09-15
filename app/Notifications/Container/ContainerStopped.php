<?php

namespace App\Notifications\Container;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContainerStopped extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public function __construct(public string $name, public Server $server, public ?string $url = null) {}

    public function via(object $notifiable): array
    {
        return setNotificationChannels($notifiable, 'status_changes');
    }

    public function toMail(): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Devlab: A resource  has been stopped unexpectedly on {$this->server->name}");
        $mail->view('emails.container-stopped', [
            'containerName' => $this->name,
            'serverName' => $this->server->name,
            'url' => $this->url,
        ]);

        return $mail;
    }

    public function toDiscord(): string
    {
        $message = "Devlab: A resource ($this->name) has been stopped unexpectedly on {$this->server->name}";

        return $message;
    }

    public function toTelegram(): array
    {
        $message = "Devlab: A resource ($this->name) has been stopped unexpectedly on {$this->server->name}";
        $payload = [
            'message' => $message,
        ];
        if ($this->url) {
            $payload['buttons'] = [
                [
                    [
                        'text' => 'Open Application in Devlab',
                        'url' => $this->url,
                    ],
                ],
            ];
        }

        return $payload;
    }
}
