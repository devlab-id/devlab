<?php

namespace App\Http\Livewire;

use App\Models\Server;
use Livewire\Component;

class RunCommand extends Component
{
    public $activity;

    public $isKeepAliveOn = false;

    public $manualKeepAlive = false;

    public $command = 'ls';

    public $server;

    public $servers = [];

    public function mount()
    {
        $this->servers = Server::all()->pluck('name')->toArray();
        $this->server = $this->servers[0];

    }
    public function render()
    {
        return view('livewire.run-command');
    }

    public function runCommand()
    {
        $this->isKeepAliveOn = true;

        $this->activity = remoteProcess($this->command, $this->server);
    }

    public function runSleepingBeauty()
    {
        $this->isKeepAliveOn = true;

        $this->activity = remoteProcess('x=1; while  [ $x -le 40 ]; do sleep 0.1 && echo "Welcome $x times" $(( x++ )); done', $this->server);
    }

    public function runDummyProjectBuild()
    {
        $this->isKeepAliveOn = true;

        $this->activity = remoteProcess(<<<EOT
        cd projects/dummy-project
        ~/.docker/cli-plugins/docker-compose build --no-cache
        EOT, $this->server);
    }

    public function polling()
    {
        $this->activity?->refresh();

        if (data_get($this->activity, 'properties.exitCode') !== null) {
            $this->isKeepAliveOn = false;
        }
    }
}
