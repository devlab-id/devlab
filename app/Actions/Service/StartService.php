<?php

namespace App\Actions\Service;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Service;
use Symfony\Component\Yaml\Yaml;

class StartService
{
    use AsAction;
    public function handle(Service $service)
    {
        $network = $service->destination->network;
        $service->saveComposeConfigs();
        $commands[] = "cd " . $service->workdir();
        $commands[] = "echo '####### Saved configuration files to {$service->workdir()}.'";
        $commands[] = "echo '####### Creating Docker network.'";
        $commands[] = "docker network create --attachable '{$service->uuid}' >/dev/null || true";
        $commands[] = "echo '####### Starting service {$service->name} on {$service->server->name}.'";
        $commands[] = "echo '####### Pulling images.'";
        $commands[] = "docker compose pull";
        $commands[] = "echo '####### Starting containers.'";
        $commands[] = "docker compose up -d --remove-orphans --force-recreate";
        $commands[] = "docker network connect $service->uuid coolify-proxy  || true";
        $compose = data_get($service,'docker_compose',[]);
        $serviceNames = data_get(Yaml::parse($compose),'services',[]);
        foreach($serviceNames as $serviceName => $serviceConfig){
            $commands[] = "docker network connect --alias {$serviceName}-{$service->uuid} $network {$serviceName}-{$service->uuid} || true";
        }
        $activity = remote_process($commands, $service->server);
        return $activity;
    }
}
