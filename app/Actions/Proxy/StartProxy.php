<?php

namespace App\Actions\Proxy;

use App\Models\Server;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Activitylog\Models\Activity;

class StartProxy
{
    use AsAction;
    public function handle(Server $server, bool $async = true): string|Activity
    {
        try {
            CheckProxy::run($server);

            $proxyType = $server->proxyType();
            $commands = collect([]);
            $proxy_path = get_proxy_path();
            $configuration = CheckConfiguration::run($server);
            if (!$configuration) {
                throw new \Exception("Configuration is not synced");
            }
            SaveConfiguration::run($server, $configuration);
            $docker_compose_yml_base64 = base64_encode($configuration);
            $server->proxy->last_applied_settings = Str::of($docker_compose_yml_base64)->pipe('md5')->value;
            $server->save();
            $commands = $commands->merge([
                "mkdir -p $proxy_path && cd $proxy_path",
                "echo 'Creating required Docker Compose file.'",
                "echo 'Pulling docker image.'",
                'docker compose pull',
                "echo 'Stopping existing coolify-proxy.'",
                "docker compose down -v --remove-orphans > /dev/null 2>&1",
                "echo 'Starting coolify-proxy.'",
                'docker compose up -d --remove-orphans',
                "echo 'Proxy started successfully.'"
            ]);
            $commands = $commands->merge(connectProxyToNetworks($server));
            if ($async) {
                $activity = remote_process($commands, $server);
                return $activity;
            } else {
                instant_remote_process($commands, $server);
                $server->proxy->set('status', 'running');
                $server->proxy->set('type', $proxyType);
                $server->save();
                return 'OK';
            }
        } catch(\Throwable $e) {
            ray($e);
            throw $e;
        }


    }
}
