<?php

namespace App\Livewire\Project\Application;

use App\Models\Application;
use Illuminate\Support\Collection;
use Livewire\Component;

class Deployments extends Component
{
    public Application $application;
    public Array|Collection $deployments = [];
    public int $deployments_count = 0;
    public string $current_url;
    public int $skip = 0;
    public int $default_take = 40;
    public bool $show_next = false;
    public ?string $pull_request_id = null;
    protected $queryString = ['pull_request_id'];
    public function mount()
    {
        $this->current_url = url()->current();
        $this->show_pull_request_only();
        $this->show_more();
    }
    private function show_pull_request_only() {
        if ($this->pull_request_id) {
            $this->deployments = $this->deployments->where('pull_request_id', $this->pull_request_id);
        }
    }
    private function show_more()
    {
        if (count($this->deployments) !== 0) {
            $this->show_next = true;
            if (count($this->deployments) < $this->default_take) {
                $this->show_next = false;
            }
            return;
        }
    }

    public function reload_deployments()
    {
        $this->load_deployments();
    }

    public function load_deployments(int|null $take = null)
    {
        if ($take) {
            $this->skip = $this->skip + $take;
        }
        $take = $this->default_take;

        ['deployments' => $deployments, 'count' => $count] = $this->application->deployments($this->skip, $take);
        $this->deployments = $deployments;
        $this->deployments_count = $count;
        $this->show_pull_request_only();
        $this->show_more();
    }
}
