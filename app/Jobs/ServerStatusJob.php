<?php

namespace App\Jobs;

use App\Models\Server;
use App\Notifications\Server\HighDiskUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ServerStatusJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int|string|null $disk_usage = null;
    public $tries = 3;
    public function backoff(): int
    {
        return isDev() ? 1 : 3;
    }
    public function __construct(public Server $server)
    {
    }
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->server->uuid))];
    }

    public function uniqueId(): int
    {
        return $this->server->uuid;
    }

    public function handle()
    {
        if (!$this->server->isServerReady($this->tries)) {
           return "Server is not ready yet.";
        };
        try {
            if ($this->server->isFunctional()) {
                $this->cleanup(notify: false);
                $this->removeCoolifyYaml();
            }
        } catch (\Throwable $e) {
            send_internal_notification('ServerStatusJob failed with: ' . $e->getMessage());
            ray($e->getMessage());
            return handleError($e);
        }
    }
    private function removeCoolifyYaml()
    {
        // This will remote the coolify.yaml file from the server as it is not needed on cloud servers
        if (isCloud() && $this->server->id !== 0) {
            $file = $this->server->proxyPath() . "/dynamic/coolify.yaml";
            return instant_remote_process([
                "rm -f $file",
            ], $this->server, false);
        }
    }
    public function cleanup(bool $notify = false): void
    {
        $this->disk_usage = $this->server->getDiskUsage();
        if ($this->disk_usage >= $this->server->settings->cleanup_after_percentage) {
            if ($notify) {
                if ($this->server->high_disk_usage_notification_sent) {
                    ray('high disk usage notification already sent');
                    return;
                } else {
                    $this->server->high_disk_usage_notification_sent = true;
                    $this->server->save();
                    $this->server->team?->notify(new HighDiskUsage($this->server, $this->disk_usage, $this->server->settings->cleanup_after_percentage));
                }
            } else {
                DockerCleanupJob::dispatchSync($this->server);
                $this->cleanup(notify: true);
            }
        } else {
            $this->server->high_disk_usage_notification_sent = false;
            $this->server->save();
        }
    }
}
