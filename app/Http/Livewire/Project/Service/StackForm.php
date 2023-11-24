<?php

namespace App\Http\Livewire\Project\Service;

use Livewire\Component;

class StackForm extends Component
{
    public $service;
    public $fields = [];
    protected $listeners = ["saveCompose"];
    public $rules = [
        'service.docker_compose_raw' => 'required',
        'service.docker_compose' => 'required',
        'service.name' => 'required',
        'service.description' => 'nullable',
    ];
    public $validationAttributes = [];
    public function mount()
    {
        $extraFields = $this->service->extraFields();
        foreach ($extraFields as $serviceName => $fields) {
            foreach ($fields as $fieldKey => $field) {
                $key = data_get($field, 'key');
                $value = data_get($field, 'value');
                $rules = data_get($field, 'rules');
                $isPassword = data_get($field, 'isPassword');
                $this->fields[$key] = [
                    "serviceName" => $serviceName,
                    "key" => $key,
                    "name" => $fieldKey,
                    "value" => $value,
                    "isPassword" => $isPassword,
                    "rules" => $rules
                ];
                $this->rules["fields.$key.value"] = $rules;
                $this->validationAttributes["fields.$key.value"] = $fieldKey;
            }
        }
    }
    public function saveCompose($raw)
    {

        $this->service->docker_compose_raw = $raw;
        $this->submit();
    }

    public function submit()
    {
        try {
            $this->validate();
            $this->service->save();
            $this->service->saveExtraFields($this->fields);
            $this->service->parse();
            $this->service->refresh();
            $this->service->saveComposeConfigs();
            $this->emit('refreshStacks');
            $this->emit('refreshEnvs');
            $this->emit('success', 'Service saved successfully.');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
    public function render()
    {
        return view('livewire.project.service.stack-form');
    }
}
