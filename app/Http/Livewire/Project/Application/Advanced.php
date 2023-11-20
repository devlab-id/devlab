<?php

namespace App\Http\Livewire\Project\Application;

use App\Models\Application;
use Livewire\Component;

class Advanced extends Component
{
    public Application $application;
    protected $rules = [
        'application.settings.is_git_submodules_enabled' => 'boolean|required',
        'application.settings.is_git_lfs_enabled' => 'boolean|required',
        'application.settings.is_preview_deployments_enabled' => 'boolean|required',
        'application.settings.is_auto_deploy_enabled' => 'boolean|required',
        'application.settings.is_force_https_enabled' => 'boolean|required',
        'application.settings.is_log_drain_enabled' => 'boolean|required',
        'application.settings.is_gpu_enabled' => 'boolean|required',
        'application.settings.gpu_driver' => 'string|required',
        'application.settings.gpu_count' => 'string|required',
        'application.settings.gpu_device_ids' => 'string|required',
        'application.settings.gpu_options' => 'string|required',
    ];
    public function instantSave()
    {
        if ($this->application->settings->is_log_drain_enabled) {
            if (!$this->application->destination->server->isLogDrainEnabled()) {
                $this->application->settings->is_log_drain_enabled = false;
                $this->emit('error', 'Log drain is not enabled on this server.');
                return;
            }
        }
        if ($this->application->settings->is_force_https_enabled) {
            $this->emit('resetDefaultLabels', false);
        }
        $this->application->settings->save();
        $this->emit('success', 'Settings saved.');
    }
    public function submit() {
        if ($this->application->settings->gpu_count && $this->application->settings->gpu_device_ids) {
            $this->emit('error', 'You cannot set both GPU count and GPU device IDs.');
            $this->application->settings->gpu_count = null;
            $this->application->settings->gpu_device_ids = null;
            $this->application->settings->save();
            return;
        }
        $this->application->settings->save();
        $this->emit('success', 'Settings saved.');
    }
    public function render()
    {
        return view('livewire.project.application.advanced');
    }
}
