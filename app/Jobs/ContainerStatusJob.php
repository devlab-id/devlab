<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ContainerStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string|null $container_id = null,
    ) {
    }
    public function handle(): void
    {
        try {
            if ($this->container_id) {
                $this->checkContainerStatus();
            } else {
                $this->checkAllServers();
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
    protected function checkAllServers()
    {
        $servers = Server::all()->reject(fn (Server $server) => $server->settings->is_build_server);
        $applications = Application::all();
        $not_found_applications = $applications;
        $containers = collect();
        foreach ($servers as $server) {
            $output = instantRemoteProcess(['docker ps -a -q --format \'{{json .}}\''], $server);
            $containers = $containers->concat(formatDockerCmdOutputToJson($output));
        }
        foreach ($containers as $container) {
            $found_application = $applications->filter(function ($value, $key) use ($container) {
                return $value->uuid == $container['Names'];
            })->first();
            if ($found_application) {
                $not_found_applications = $not_found_applications->filter(function ($value, $key) use ($found_application) {
                    return $value->uuid != $found_application->uuid;
                });
                $found_application->status = $container['State'];
                $found_application->save();
                Log::info('Found application: ' . $found_application->uuid . '. Set status to: ' . $found_application->status);
            }
        }
        foreach ($not_found_applications as $not_found_application) {
            $not_found_application->status = 'exited';
            $not_found_application->save();
            Log::info('Not found application: ' . $not_found_application->uuid . '. Set status to: ' . $not_found_application->status);
        }
    }
    protected function checkContainerStatus()
    {
        $application = Application::where('uuid', $this->container_id)->firstOrFail();
        if (!$application) {
            return;
        }
        if ($application->destination->server) {
            $container = instantRemoteProcess(["docker inspect --format '{{json .State}}' {$this->container_id}"], $application->destination->server);
            $container = formatDockerCmdOutputToJson($container);
            $application->status = $container[0]['Status'];
            $application->save();
        }
    }
}
