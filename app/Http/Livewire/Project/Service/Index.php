<?php

namespace App\Http\Livewire\Project\Service;

use App\Jobs\ContainerStatusJob;
use App\Models\Service;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Livewire\Component;

class Index extends Component
{
    use WithRateLimiting;
    public Service $service;
    public $applications;
    public $databases;
    public array $parameters;
    public array $query;
    protected $rules = [
        'service.docker_compose_raw' => 'required',
        'service.docker_compose' => 'required',
        'service.name' => 'required',
        'service.description' => 'nullable',
    ];
    public function manualRefreshStack() {
        try {
            $this->rateLimit(5);
            dispatch_sync(new ContainerStatusJob($this->service->server));
            $this->refreshStack();
        } catch(\Throwable $e) {
            return handleError($e, $this);
        }
    }
    public function refreshStack()
    {
        $this->applications = $this->service->applications->sort();
        $this->applications->each(function ($application) {
            $application->configuration_required = $application->configurationRequired();
        });
        $this->databases = $this->service->databases->sort();
        $this->databases->each(function ($database) {
            $database->configuration_required = $database->configurationRequired();
        });
        $this->emit('success', 'Stack refreshed successfully.');
    }
    public function mount()
    {
        $this->parameters = get_route_parameters();
        $this->query = request()->query();
        $this->service = Service::whereUuid($this->parameters['service_uuid'])->firstOrFail();
        $this->refreshStack();
    }
    public function render()
    {
        return view('livewire.project.service.index');
    }
    public function submit()
    {
        try {
            $this->validate();
            $this->service->save();
            $this->service->parse();
            $this->service->refresh();
            $this->service->saveComposeConfigs();
            $this->refreshStack();
            $this->emit('refreshEnvs');
            $this->emit('success', 'Service saved successfully.');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
