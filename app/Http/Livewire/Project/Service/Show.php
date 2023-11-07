<?php

namespace App\Http\Livewire\Project\Service;

use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use Illuminate\Support\Collection;
use Livewire\Component;

class Show extends Component
{
    public Service $service;
    public ?ServiceApplication $serviceApplication = null;
    public ?ServiceDatabase $serviceDatabase = null;
    public array $parameters;
    public array $query;
    public Collection $services;
    public $s3s;

    protected $listeners = ['generateDockerCompose'];

    public function mount()
    {
        try {
            $this->services = collect([]);
            $this->parameters = get_route_parameters();
            $this->query = request()->query();
            $this->service = Service::whereUuid($this->parameters['service_uuid'])->firstOrFail();
            $service = $this->service->applications()->whereName($this->parameters['service_name'])->first();
            if ($service) {
                $this->serviceApplication = $service;
                $this->serviceApplication->getFilesFromServer();
            } else {
                $this->serviceDatabase = $this->service->databases()->whereName($this->parameters['service_name'])->first();
                $this->serviceDatabase->getFilesFromServer();
            }
            $this->s3s = currentTeam()->s3s;
        } catch(\Throwable $e) {
            return handleError($e, $this);
        }

    }
    public function generateDockerCompose()
    {
        $this->service->parse();
    }
    public function render()
    {
        return view('livewire.project.service.show');
    }
}
