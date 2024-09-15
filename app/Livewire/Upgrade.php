<?php

namespace App\Livewire;

use App\Actions\Server\UpdateDevlab;
use App\Models\InstanceSettings;
use Livewire\Component;

class Upgrade extends Component
{
    public bool $showProgress = false;

    public bool $updateInProgress = false;

    public bool $isUpgradeAvailable = false;

    public string $latestVersion = '';

    protected $listeners = ['updateAvailable' => 'checkUpdate'];

    public function checkUpdate()
    {
        try {
            $this->latestVersion = get_latest_version_of_devlab();
            $this->isUpgradeAvailable = data_get(InstanceSettings::get(), 'new_version_available', false);

        } catch (\Throwable $e) {
            return handleError($e, $this);
        }

    }

    public function upgrade()
    {
        try {
            if ($this->updateInProgress) {
                return;
            }
            $this->updateInProgress = true;
            UpdateDevlab::run(manual_update: true);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
