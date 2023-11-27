<?php

use App\Models\Application;
use App\Models\ApplicationPreview;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Url\Url;
use Visus\Cuid2\Cuid2;

function getCurrentApplicationContainerStatus(Server $server, int $id, ?int $pullRequestId = null): Collection
{
    $containers = collect([]);
    $containers = instant_remote_process(["docker ps -a --filter='label=coolify.applicationId={$id}' --format '{{json .}}' "], $server);
    $containers = format_docker_command_output_to_json($containers);
    $containers = $containers->map(function ($container) use ($pullRequestId) {
        $labels = data_get($container, 'Labels');
        if (!str($labels)->contains("coolify.pullRequestId=")) {
            data_set($container, 'Labels', $labels . ",coolify.pullRequestId={$pullRequestId}");
            return $container;
        }
        if (str($labels)->contains("coolify.pullRequestId=$pullRequestId")) {
            return $container;
        }
        return null;
    });
    $containers = $containers->filter();
    return $containers;
}

function format_docker_command_output_to_json($rawOutput): Collection
{
    $outputLines = explode(PHP_EOL, $rawOutput);
    if (count($outputLines) === 1) {
        $outputLines = collect($outputLines[0]);
    } else {
        $outputLines = collect($outputLines);
    }
    return $outputLines
        ->reject(fn ($line) => empty($line))
        ->map(fn ($outputLine) => json_decode($outputLine, true, flags: JSON_THROW_ON_ERROR));
}

function format_docker_labels_to_json(string|array $rawOutput): Collection
{
    if (is_array($rawOutput)) {
        return collect($rawOutput);
    }
    $outputLines = explode(PHP_EOL, $rawOutput);

    return collect($outputLines)
        ->reject(fn ($line) => empty($line))
        ->map(function ($outputLine) {
            $outputArray = explode(',', $outputLine);
            return collect($outputArray)
                ->map(function ($outputLine) {
                    return explode('=', $outputLine);
                })
                ->mapWithKeys(function ($outputLine) {
                    return [$outputLine[0] => $outputLine[1]];
                });
        })[0];
}

function format_docker_envs_to_json($rawOutput)
{
    try {
        $outputLines = json_decode($rawOutput, true, flags: JSON_THROW_ON_ERROR);
        return collect(data_get($outputLines[0], 'Config.Env', []))->mapWithKeys(function ($env) {
            $env = explode('=', $env);
            return [$env[0] => $env[1]];
        });
    } catch (\Throwable $e) {
        return collect([]);
    }
}
function checkMinimumDockerEngineVersion($dockerVersion)
{
    $majorDockerVersion = Str::of($dockerVersion)->before('.')->value();
    if ($majorDockerVersion <= 22) {
        $dockerVersion = null;
    }
    return $dockerVersion;
}
function executeInDocker(string $containerId, string $command)
{
    return "docker exec {$containerId} bash -c '{$command}'";
    // return "docker exec {$this->deployment_uuid} bash -c '{$command} |& tee -a /proc/1/fd/1; [ \$PIPESTATUS -eq 0 ] || exit \$PIPESTATUS'";
}

function getContainerStatus(Server $server, string $container_id, bool $all_data = false, bool $throwError = false)
{
    $container = instant_remote_process(["docker inspect --format '{{json .}}' {$container_id}"], $server, $throwError);
    if (!$container) {
        return 'exited';
    }
    $container = format_docker_command_output_to_json($container);
    if ($all_data) {
        return $container[0];
    }
    return data_get($container[0], 'State.Status', 'exited');
}

function generateApplicationContainerName(Application $application, $pull_request_id = 0)
{
    $now = now()->format('Hisu');
    if ($pull_request_id !== 0 && $pull_request_id !== null) {
        return $application->uuid . '-pr-' . $pull_request_id;
    } else {
        return $application->uuid . '-' . $now;
    }
}
function get_port_from_dockerfile($dockerfile): int|null
{
    $dockerfile_array = explode("\n", $dockerfile);
    $found_exposed_port = null;
    foreach ($dockerfile_array as $line) {
        $line_str = Str::of($line)->trim();
        if ($line_str->startsWith('EXPOSE')) {
            $found_exposed_port = $line_str->replace('EXPOSE', '')->trim();
            break;
        }
    }
    if ($found_exposed_port) {
        return (int)$found_exposed_port->value();
    }
    return null;
}

function defaultLabels($id, $name, $pull_request_id = 0, string $type = 'application', $subType = null, $subId = null)
{
    $labels = collect([]);
    $labels->push('coolify.managed=true');
    $labels->push('coolify.version=' . config('version'));
    $labels->push("coolify." . $type . "Id=" . $id);
    $labels->push("coolify.type=$type");
    $labels->push('coolify.name=' . $name);
    $labels->push('coolify.pullRequestId=' . $pull_request_id);
    if ($type === 'service') {
        $subId && $labels->push('coolify.service.subId=' . $subId);
        $subType && $labels->push('coolify.service.subType=' . $subType);
    }
    return $labels;
}
function generateServiceSpecificFqdns(ServiceApplication|Application $resource, $forTraefik = false)
{
    if ($resource->getMorphClass() === 'App\Models\ServiceApplication') {
        $uuid = $resource->uuid;
        $server = $resource->service->server;
        $environment_variables = $resource->service->environment_variables;
        $type = $resource->serviceType();
    } else if ($resource->getMorphClass() === 'App\Models\Application') {
        $uuid = $resource->uuid;
        $server = $resource->destination->server;
        $environment_variables = $resource->environment_variables;
        $type = $resource->serviceType();
    }
    $variables = collect($environment_variables);
    $payload = collect([]);
    switch ($type) {
        case $type?->contains('minio'):
            $MINIO_BROWSER_REDIRECT_URL = $variables->where('key', 'MINIO_BROWSER_REDIRECT_URL')->first();
            $MINIO_SERVER_URL = $variables->where('key', 'MINIO_SERVER_URL')->first();
            if (is_null($MINIO_BROWSER_REDIRECT_URL) || is_null($MINIO_SERVER_URL)) {
                return $payload;
            }
            if (is_null($MINIO_BROWSER_REDIRECT_URL?->value)) {
                $MINIO_BROWSER_REDIRECT_URL?->update([
                    "value" => generateFqdn($server, 'console-' . $uuid)
                ]);
            }
            if (is_null($MINIO_SERVER_URL?->value)) {
                $MINIO_SERVER_URL?->update([
                    "value" => generateFqdn($server, 'minio-' . $uuid)
                ]);
            }
            if ($forTraefik) {
                $payload = collect([
                    $MINIO_BROWSER_REDIRECT_URL->value . ':9001',
                    $MINIO_SERVER_URL->value . ':9000',
                ]);
            } else {
                $payload = collect([
                    $MINIO_BROWSER_REDIRECT_URL->value,
                    $MINIO_SERVER_URL->value,
                ]);
            }
            break;
    }
    return $payload;
}
function fqdnLabelsForTraefik(string $uuid, Collection $domains, bool $is_force_https_enabled, $onlyPort = null)
{
    $labels = collect([]);
    $labels->push('traefik.enable=true');
    foreach ($domains as $loop => $domain) {
        $uuid = new Cuid2(7);
        $url = Url::fromString($domain);
        $host = $url->getHost();
        $path = $url->getPath();
        $schema = $url->getScheme();
        $port = $url->getPort();
        if (is_null($port) && !is_null($onlyPort)) {
            $port = $onlyPort;
        }
        $http_label = "{$uuid}-{$loop}-http";
        $https_label = "{$uuid}-{$loop}-https";

        if ($schema === 'https') {
            // Set labels for https
            $labels->push("traefik.http.routers.{$https_label}.rule=Host(`{$host}`) && PathPrefix(`{$path}`)");
            $labels->push("traefik.http.routers.{$https_label}.entryPoints=https");
            $labels->push("traefik.http.routers.{$https_label}.middlewares=gzip");
            if ($port) {
                $labels->push("traefik.http.routers.{$https_label}.service={$https_label}");
                $labels->push("traefik.http.services.{$https_label}.loadbalancer.server.port=$port");
            }
            if ($path !== '/') {
                $labels->push("traefik.http.routers.{$https_label}.middlewares={$https_label}-stripprefix");
                $labels->push("traefik.http.middlewares.{$https_label}-stripprefix.stripprefix.prefixes={$path}");
            }

            $labels->push("traefik.http.routers.{$https_label}.tls=true");
            $labels->push("traefik.http.routers.{$https_label}.tls.certresolver=letsencrypt");

            // Set labels for http (redirect to https)
            $labels->push("traefik.http.routers.{$http_label}.rule=Host(`{$host}`) && PathPrefix(`{$path}`)");
            $labels->push("traefik.http.routers.{$http_label}.entryPoints=http");
            if ($is_force_https_enabled) {
                $labels->push("traefik.http.routers.{$http_label}.middlewares=redirect-to-https");
            }
        } else {
            // Set labels for http
            $labels->push("traefik.http.routers.{$http_label}.rule=Host(`{$host}`) && PathPrefix(`{$path}`)");
            $labels->push("traefik.http.routers.{$http_label}.entryPoints=http");
            $labels->push("traefik.http.routers.{$http_label}.middlewares=gzip");
            if ($port) {
                $labels->push("traefik.http.routers.{$http_label}.service={$http_label}");
                $labels->push("traefik.http.services.{$http_label}.loadbalancer.server.port=$port");
            }
            if ($path !== '/') {
                $labels->push("traefik.http.routers.{$http_label}.middlewares={$http_label}-stripprefix");
                $labels->push("traefik.http.middlewares.{$http_label}-stripprefix.stripprefix.prefixes={$path}");
            }
        }
    }

    return $labels;
}
function generateLabelsApplication(Application $application, ?ApplicationPreview $preview = null): array
{
    $ports = $application->settings->is_static ? [80] : $application->ports_exposes_array;
    $onlyPort = null;
    if (count($ports) === 1) {
        $onlyPort = $ports[0];
    }
    $pull_request_id = data_get($preview, 'pull_request_id', 0);
    $appUuid = $application->uuid;
    if ($pull_request_id !== 0) {
        $appUuid = $appUuid . '-pr-' . $pull_request_id;
    }
    $labels = collect([]);
    if ($application->fqdn) {
        if ($pull_request_id !== 0) {
            $domains = Str::of(data_get($preview, 'fqdn'))->explode(',');
        } else {
            $domains = Str::of(data_get($application, 'fqdn'))->explode(',');
        }
        // Add Traefik labels no matter which proxy is selected
        $labels = $labels->merge(fqdnLabelsForTraefik($appUuid, $domains, $application->settings->is_force_https_enabled, $onlyPort));
    }
    return $labels->all();
}

function isDatabaseImage(string $image)
{
    $image = str($image);
    if ($image->contains(':')) {
        $image = str($image);
    } else {
        $image = str($image)->append(':latest');
    }
    $imageName = $image->before(':');
    if (collect(DATABASE_DOCKER_IMAGES)->contains($imageName)) {
        return true;
    }
    return false;
}
