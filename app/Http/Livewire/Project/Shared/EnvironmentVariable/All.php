<?php

namespace App\Http\Livewire\Project\Shared\EnvironmentVariable;

use App\Models\EnvironmentVariable;
use Livewire\Component;
use Visus\Cuid2\Cuid2;
use Illuminate\Support\Str;

class All extends Component
{
    public $resource;
    public bool $showPreview = false;
    public string|null $modalId = null;
    public ?string $variables = null;
    public ?string $variablesPreview = null;
    public string $view = 'normal';
    protected $listeners = ['refreshEnvs', 'submit'];

    public function mount()
    {
        $resourceClass = get_class($this->resource);
        $resourceWithPreviews = ['App\Models\Application'];
        $simpleDockerfile = !is_null(data_get($this->resource, 'dockerfile'));
        if (Str::of($resourceClass)->contains($resourceWithPreviews) && !$simpleDockerfile) {
            $this->showPreview = true;
        }
        $this->modalId = new Cuid2(7);
        $this->getDevView();
    }
    public function getDevView()
    {
        $this->variables = $this->resource->environment_variables->map(function ($item) {
            if ($item->is_shown_once) {
                return "$item->key=(locked secret)";
            }
            return "$item->key=$item->value";
        })->sort()->join('
');
        if ($this->showPreview) {
            $this->variablesPreview = $this->resource->environment_variables_preview->map(function ($item) {
                if ($item->is_shown_once) {
                    return "$item->key=(locked secret)";
                }
                return "$item->key=$item->value";
            })->sort()->join('
');
        }
    }
    public function switch()
    {
        $this->view = $this->view === 'normal' ? 'dev' : 'normal';
    }
    public function saveVariables($isPreview)
    {
        if ($isPreview) {
            $variables = parseEnvFormatToArray($this->variablesPreview);
            $this->resource->environment_variables_preview()->whereNotIn('key', array_keys($variables))->delete();
        } else {
            $variables = parseEnvFormatToArray($this->variables);
            $this->resource->environment_variables()->whereNotIn('key', array_keys($variables))->delete();
        }
        foreach ($variables as $key => $variable) {
            if ($isPreview) {
                $found = $this->resource->environment_variables_preview()->where('key', $key)->first();
            } else {
                $found = $this->resource->environment_variables()->where('key', $key)->first();
            }
            if ($found) {
                if ($found->is_shown_once) {
                    continue;
                }
                $found->value = $variable;
                $found->save();
                continue;
            } else {
                $environment = new EnvironmentVariable();
                $environment->key = $key;
                $environment->value = $variable;
                $environment->is_build_time = false;
                $environment->is_preview = $isPreview ? true : false;
                switch ($this->resource->type()) {
                    case 'application':
                        $environment->application_id = $this->resource->id;
                        break;
                    case 'standalone-postgresql':
                        $environment->standalone_postgresql_id = $this->resource->id;
                        break;
                    case 'standalone-redis':
                        $environment->standalone_redis_id = $this->resource->id;
                        break;
                    case 'standalone-mongodb':
                        $environment->standalone_mongodb_id = $this->resource->id;
                        break;
                    case 'standalone-mysql':
                        $environment->standalone_mysql_id = $this->resource->id;
                        break;
                    case 'standalone-mariadb':
                        $environment->standalone_mariadb_id = $this->resource->id;
                        break;
                    case 'service':
                        $environment->service_id = $this->resource->id;
                        break;
                }
                $environment->save();
            }
        }
        if ($isPreview) {
            $this->emit('success', 'Preview environment variables updated successfully.');
        } else {
            $this->emit('success', 'Environment variables updated successfully.');
        }
        $this->refreshEnvs();
    }
    public function refreshEnvs()
    {
        $this->resource->refresh();
        $this->getDevView();
    }

    public function submit($data)
    {
        try {
            $found = $this->resource->environment_variables()->where('key', $data['key'])->first();
            if ($found) {
                $this->emit('error', 'Environment variable already exists.');
                return;
            }
            $environment = new EnvironmentVariable();
            $environment->key = $data['key'];
            $environment->value = $data['value'];
            $environment->is_build_time = $data['is_build_time'];
            $environment->is_preview = $data['is_preview'];

            switch ($this->resource->type()) {
                case 'application':
                    $environment->application_id = $this->resource->id;
                    break;
                case 'standalone-postgresql':
                    $environment->standalone_postgresql_id = $this->resource->id;
                    break;
                case 'service':
                    $environment->service_id = $this->resource->id;
                    break;
            }
            $environment->save();
            $this->refreshEnvs();
            $this->emit('success', 'Environment variable added successfully.');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
