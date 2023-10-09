<?php

namespace App\Jobs;

use App\Actions\Proxy\StartProxy;
use App\Models\ApplicationPreview;
use App\Models\Server;
use App\Notifications\Container\ContainerRestarted;
use App\Notifications\Container\ContainerStopped;
use App\Notifications\Server\Revived;
use App\Notifications\Server\Unreachable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ContainerStatusJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 120;

    public function __construct(public Server $server)
    {
        $this->handle();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->server->uuid))->dontRelease()];
    }

    public function uniqueId(): string
    {
        return $this->server->uuid;
    }
    public function handle()
    {
        try {
            ray("checking server status for {$this->server->name}");
            // ray()->clearAll();
            $serverUptimeCheckNumber = 0;
            $serverUptimeCheckNumberMax = 3;
            while (true) {
                ray('checking # ' . $serverUptimeCheckNumber);
                if ($serverUptimeCheckNumber >= $serverUptimeCheckNumberMax) {
                    send_internal_notification('Server unreachable: ' . $this->server->name);
                    if ($this->server->unreachable_email_sent === false) {
                        ray('Server unreachable, sending notification...');
                        $this->server->team->notify(new Unreachable($this->server));
                    }
                    $this->server->settings()->update([
                        'is_reachable' => false,
                    ]);
                    $this->server->update(['unreachable_email_sent' => true]);
                    return;
                }
                $result = $this->server->validateConnection();
                if ($result) {
                    break;
                }
                $serverUptimeCheckNumber++;
                sleep(5);
            }
            if (data_get($this->server, 'unreachable_email_sent') === true) {
                ray('Server is reachable again, sending notification...');
                $this->server->team->notify(new Revived($this->server));
                $this->server->update(['unreachable_email_sent' => false]);
            }
            if (
                data_get($this->server, 'settings.is_reachable') === false ||
                data_get($this->server, 'settings.is_usable') === false
            ) {
                $this->server->settings()->update([
                    'is_reachable' => true,
                    'is_usable' => true
                ]);
            }
            $this->server->validateDockerEngine(true);
            $containers = instant_remote_process(["docker container ls -q"], $this->server);
            if (!$containers) {
                return;
            }
            $containers = instant_remote_process(["docker container inspect $(docker container ls -q) --format '{{json .}}'"], $this->server);
            $containers = format_docker_command_output_to_json($containers);
            $applications = $this->server->applications();
            $databases = $this->server->databases();
            $services = $this->server->services()->get();
            $previews = $this->server->previews();
            $this->server->proxyType();
            /// Check if proxy is running
            $foundProxyContainer = $containers->filter(function ($value, $key) {
                return data_get($value, 'Name') === '/coolify-proxy';
            })->first();
            if (!$foundProxyContainer) {
                ray('Proxy not found, starting it...');
                if ($this->server->isProxyShouldRun()) {
                    StartProxy::run($this->server, false);
                    $this->server->team->notify(new ContainerRestarted('coolify-proxy', $this->server));
                }
            } else {
                $this->server->proxy->status = data_get($foundProxyContainer, 'State.Status');
                $this->server->save();
                $connectProxyToDockerNetworks = connectProxyToNetworks($this->server);
                instant_remote_process($connectProxyToDockerNetworks, $this->server, false);
            }
            $foundApplications = [];
            $foundApplicationPreviews = [];
            $foundDatabases = [];
            $foundServices = [];

            foreach ($containers as $container) {
                $containerStatus = data_get($container, 'State.Status');
                $containerHealth = data_get($container, 'State.Health.Status', 'unhealthy');
                $containerStatus = "$containerStatus ($containerHealth)";
                $labels = data_get($container, 'Config.Labels');
                $labels = Arr::undot(format_docker_labels_to_json($labels));
                $labelId = data_get($labels, 'coolify.applicationId');
                if ($labelId) {
                    if (str_contains($labelId, '-pr-')) {
                        $pullRequestId = data_get($labels, 'coolify.pullRequestId');
                        $applicationId = (int) Str::before($labelId, '-pr-');
                        $preview = ApplicationPreview::where('application_id', $applicationId)->where('pull_request_id', $pullRequestId)->first();
                        if ($preview) {
                            $foundApplicationPreviews[] = $preview->id;
                            $statusFromDb = $preview->status;
                            if ($statusFromDb !== $containerStatus) {
                                $preview->update(['status' => $containerStatus]);
                            }
                        } else {
                            //Notify user that this container should not be there.
                        }
                    } else {
                        $application = $applications->where('id', $labelId)->first();
                        if ($application) {
                            $foundApplications[] = $application->id;
                            $statusFromDb = $application->status;
                            if ($statusFromDb !== $containerStatus) {
                                $application->update(['status' => $containerStatus]);
                            }
                        } else {
                            //Notify user that this container should not be there.
                        }
                    }
                } else {
                    $uuid = data_get($labels, 'com.docker.compose.service');
                    if ($uuid) {
                        $database = $databases->where('uuid', $uuid)->first();
                        if ($database) {
                            $foundDatabases[] = $database->id;
                            $statusFromDb = $database->status;
                            if ($statusFromDb !== $containerStatus) {
                                $database->update(['status' => $containerStatus]);
                            }
                        } else {
                            // Notify user that this container should not be there.
                        }
                    }
                }
                $serviceLabelId = data_get($labels, 'coolify.serviceId');
                if ($serviceLabelId) {
                    $subType = data_get($labels, 'coolify.service.subType');
                    $subId = data_get($labels, 'coolify.service.subId');
                    $service = $services->where('id', $serviceLabelId)->first();
                    if (!$service) {
                        continue;
                    }
                    if ($subType === 'application') {
                        $service =  $service->applications()->where('id', $subId)->first();
                    } else {
                        $service =  $service->databases()->where('id', $subId)->first();
                    }
                    if ($service) {
                        $foundServices[] = "$service->id-$service->name";
                        $statusFromDb = $service->status;
                        if ($statusFromDb !== $containerStatus) {
                            // ray('Updating status: ' . $containerStatus);
                            $service->update(['status' => $containerStatus]);
                        }
                    }
                }
            }
            $exitedServices = collect([]);
            foreach ($services as $service) {
                $apps = $service->applications()->get();
                $dbs = $service->databases()->get();
                foreach ($apps as $app) {
                    if (in_array("$app->id-$app->name", $foundServices)) {
                        continue;
                    } else {
                        $exitedServices->push($app);
                    }
                }
                foreach ($dbs as $db) {
                    if (in_array("$db->id-$db->name", $foundServices)) {
                        continue;
                    } else {
                        $exitedServices->push($db);
                    }
                }
            }
            $exitedServices = $exitedServices->unique('id');
            foreach ($exitedServices as $exitedService) {
                if ($exitedService->status === 'exited') {
                    continue;
                }
                $name = data_get($exitedService, 'name');
                $fqdn = data_get($exitedService, 'fqdn');
                $containerName = $name ? "$name ($fqdn)" : $fqdn;
                $project = data_get($service, 'environment.project');
                $environment = data_get($service, 'environment');

                $url =  base_url() . '/project/' . $project->uuid . "/" . $environment->name . "/service/" . $service->uuid;
                $this->server->team->notify(new ContainerStopped($containerName, $this->server, $url));
                $exitedService->update(['status' => 'exited']);
            }

            $notRunningApplications = $applications->pluck('id')->diff($foundApplications);
            foreach ($notRunningApplications as $applicationId) {
                $application = $applications->where('id', $applicationId)->first();
                if ($application->status === 'exited') {
                    continue;
                }
                $application->update(['status' => 'exited']);

                $name = data_get($application, 'name');
                $fqdn = data_get($application, 'fqdn');

                $containerName = $name ? "$name ($fqdn)" : $fqdn;

                $project = data_get($application, 'environment.project');
                $environment = data_get($application, 'environment');

                $url =  base_url() . '/project/' . $project->uuid . "/" . $environment->name . "/application/" . $application->uuid;

                $this->server->team->notify(new ContainerStopped($containerName, $this->server, $url));
            }
            $notRunningApplicationPreviews = $previews->pluck('id')->diff($foundApplicationPreviews);
            foreach ($notRunningApplicationPreviews as $previewId) {
                $preview = $previews->where('id', $previewId)->first();
                if ($preview->status === 'exited') {
                    continue;
                }
                $preview->update(['status' => 'exited']);

                $name = data_get($preview, 'name');
                $fqdn = data_get($preview, 'fqdn');

                $containerName = $name ? "$name ($fqdn)" : $fqdn;

                $project = data_get($preview, 'application.environment.project');
                $environment = data_get($preview, 'application.environment');

                $url =  base_url() . '/project/' . $project->uuid . "/" . $environment->name . "/application/" . $preview->application->uuid;
                $this->server->team->notify(new ContainerStopped($containerName, $this->server, $url));
            }
            $notRunningDatabases = $databases->pluck('id')->diff($foundDatabases);
            foreach ($notRunningDatabases as $database) {
                $database = $databases->where('id', $database)->first();
                if ($database->status === 'exited') {
                    continue;
                }
                $database->update(['status' => 'exited']);

                $name = data_get($database, 'name');
                $fqdn = data_get($database, 'fqdn');

                $containerName = $name;

                $project = data_get($database, 'environment.project');
                $environment = data_get($database, 'environment');

                $url =  base_url() . '/project/' . $project->uuid . "/" . $environment->name . "/database/" . $database->uuid;
                $this->server->team->notify(new ContainerStopped($containerName, $this->server, $url));
            }
        } catch (\Throwable $e) {
            send_internal_notification('ContainerStatusJob failed with: ' . $e->getMessage());
            ray($e->getMessage());
            return handleError($e);
        }
    }
}
