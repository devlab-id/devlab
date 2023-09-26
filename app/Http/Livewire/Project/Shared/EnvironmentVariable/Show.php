<?php

namespace App\Http\Livewire\Project\Shared\EnvironmentVariable;

use App\Models\EnvironmentVariable as ModelsEnvironmentVariable;
use Livewire\Component;
use Visus\Cuid2\Cuid2;

class Show extends Component
{
    public $parameters;
    public ModelsEnvironmentVariable $env;
    public ?string $modalId = null;
    public string $type;

    protected $rules = [
        'env.key' => 'required|string',
        'env.value' => 'nullable',
        'env.is_build_time' => 'required|boolean',
    ];
    protected $validationAttributes = [
        'key' => 'key',
        'value' => 'value',
        'is_build_time' => 'build',
    ];

    public function mount()
    {
        $this->modalId = new Cuid2(7);
        $this->parameters = get_route_parameters();
    }

    public function instantSave()
    {
        $this->submit();
    }
    public function submit()
    {
        $this->validate();
        $this->env->save();
        $this->emit('success', 'Environment variable updated successfully.');
        $this->emit('refreshEnvs');
    }

    public function delete()
    {
        $this->env->delete();
        $this->emit('refreshEnvs');
    }
}
