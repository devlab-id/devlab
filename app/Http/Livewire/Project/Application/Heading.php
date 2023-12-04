<?php

namespace App\Http\Livewire\Project\Application;

use App\Actions\Application\StopApplication;
use App\Jobs\ContainerStatusJob;
use App\Jobs\ServerStatusJob;
use App\Models\Application;
use Livewire\Component;
use Visus\Cuid2\Cuid2;

class Heading extends Component
{
    protected string $deploymentUuid;
    public Application $application;
    public array $parameters;
    public function getListeners()
    {
        $teamId = auth()->user()->currentTeam()->id;
        return [
            "echo-private:custom.{$teamId},ApplicationDeploymentFinished" => 'updateStatus',
        ];
    }

    public function updateStatus($message)
    {
        $applicationUuid = data_get($message, 'applicationUuid');
        if ($this->application->uuid === $applicationUuid) {
            $this->application->status = data_get($message, 'status');
        }
    }
    public function mount()
    {
        $this->parameters = get_route_parameters();
    }

    public function check_status()
    {
        if ($this->application->destination->server->isFunctional()) {
            dispatch(new ContainerStatusJob($this->application->destination->server));
            $this->application->refresh();
            $this->application->previews->each(function ($preview) {
                $preview->refresh();
            });
        } else {
            dispatch(new ServerStatusJob($this->application->destination->server));
        }
    }

    public function force_deploy_without_cache()
    {
        $this->deploy(force_rebuild: true);
    }

    public function deployNew()
    {
        if ($this->application->build_pack === 'dockercompose' && is_null($this->application->docker_compose_raw)) {
            $this->emit('error', 'Please load a Compose file first.');
            return;
        }
        $this->setDeploymentUuid();
        queue_application_deployment(
            application_id: $this->application->id,
            deployment_uuid: $this->deploymentUuid,
            force_rebuild: false,
            is_new_deployment: true,
        );
        return redirect()->route('project.application.deployment', [
            'project_uuid' => $this->parameters['project_uuid'],
            'application_uuid' => $this->parameters['application_uuid'],
            'deployment_uuid' => $this->deploymentUuid,
            'environment_name' => $this->parameters['environment_name'],
        ]);
    }
    public function deploy(bool $force_rebuild = false)
    {
        if ($this->application->build_pack === 'dockercompose' && is_null($this->application->docker_compose_raw)) {
            $this->emit('error', 'Please load a Compose file first.');
            return;
        }
        $this->setDeploymentUuid();
        queue_application_deployment(
            application_id: $this->application->id,
            deployment_uuid: $this->deploymentUuid,
            force_rebuild: $force_rebuild,
        );
        return redirect()->route('project.application.deployment', [
            'project_uuid' => $this->parameters['project_uuid'],
            'application_uuid' => $this->parameters['application_uuid'],
            'deployment_uuid' => $this->deploymentUuid,
            'environment_name' => $this->parameters['environment_name'],
        ]);
    }

    protected function setDeploymentUuid()
    {
        $this->deploymentUuid = new Cuid2(7);
        $this->parameters['deployment_uuid'] = $this->deploymentUuid;
    }

    public function stop()
    {
        StopApplication::run($this->application);
        $this->application->status = 'exited';
        $this->application->save();
        $this->application->refresh();
    }
    public function restartNew()
    {
        $this->setDeploymentUuid();
        queue_application_deployment(
            application_id: $this->application->id,
            deployment_uuid: $this->deploymentUuid,
            restart_only: true,
            is_new_deployment: true,
        );
        return redirect()->route('project.application.deployment', [
            'project_uuid' => $this->parameters['project_uuid'],
            'application_uuid' => $this->parameters['application_uuid'],
            'deployment_uuid' => $this->deploymentUuid,
            'environment_name' => $this->parameters['environment_name'],
        ]);
    }
    public function restart()
    {
        $this->setDeploymentUuid();
        queue_application_deployment(
            application_id: $this->application->id,
            deployment_uuid: $this->deploymentUuid,
            restart_only: true,
        );
        return redirect()->route('project.application.deployment', [
            'project_uuid' => $this->parameters['project_uuid'],
            'application_uuid' => $this->parameters['application_uuid'],
            'deployment_uuid' => $this->deploymentUuid,
            'environment_name' => $this->parameters['environment_name'],
        ]);
    }
}
