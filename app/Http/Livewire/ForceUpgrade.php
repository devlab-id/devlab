<?php

namespace App\Http\Livewire;

use App\Enums\ActivityTypes;
use App\Models\Server;
use Livewire\Component;

class ForceUpgrade extends Component
{
    public function upgrade()
    {
        if (config('app.env') === 'local') {
            $server = Server::where('ip', 'coolify-testing-host')->first();
            if (!$server) {
                return;
            }
            instant_remote_process([
                "sleep 2"
            ], $server);
            remote_process([
                "sleep 10"
            ], $server, ActivityTypes::INLINE->value);
            $this->emit('updateInitiated');
        } else {
            $latestVersion = get_latest_version_of_coolify();

            $cdn = "https://coolify-cdn.b-cdn.net/files";
            $server = Server::where('ip', 'host.docker.internal')->first();
            if (!$server) {
                return;
            }

            instant_remote_process([
                "curl -fsSL $cdn/docker-compose.yml -o /data/coolify/source/docker-compose.yml",
                "curl -fsSL $cdn/docker-compose.prod.yml -o /data/coolify/source/docker-compose.prod.yml",
                "curl -fsSL $cdn/.env.production -o /data/coolify/source/.env.production",
                "curl -fsSL $cdn/upgrade.sh -o /data/coolify/source/upgrade.sh",
            ], $server);

            instant_remote_process([
                "docker compose -f /data/coolify/source/docker-compose.yml -f /data/coolify/source/docker-compose.prod.yml pull",
            ], $server);

            remote_process([
                "bash /data/coolify/source/upgrade.sh $latestVersion"
            ], $server, ActivityTypes::INLINE->value);

            $this->emit('updateInitiated');
        }
    }
}
