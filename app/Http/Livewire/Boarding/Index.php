<?php

namespace App\Http\Livewire\Boarding;

use App\Actions\Server\InstallDocker;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public string $currentState = 'welcome';

    public ?string $selectedServerType = null;
    public ?Collection $privateKeys = null;
    public ?int $selectedExistingPrivateKey = null;
    public ?string $privateKeyType = null;
    public ?string $privateKey = null;
    public ?string $publicKey = null;
    public ?string $privateKeyName = null;
    public ?string $privateKeyDescription = null;
    public ?PrivateKey $createdPrivateKey = null;

    public ?Collection $servers = null;
    public ?int $selectedExistingServer = null;
    public ?string $remoteServerName = null;
    public ?string $remoteServerDescription = null;
    public ?string $remoteServerHost = null;
    public ?int    $remoteServerPort = 22;
    public ?string $remoteServerUser = 'root';
    public ?Server $createdServer = null;

    public Collection|array $projects = [];
    public ?int $selectedExistingProject = null;
    public ?Project $createdProject = null;

    public bool $dockerInstallationStarted = false;

    public string $serverPublicKey;
    public bool $serverReachable = true;

    public function mount()
    {
        $this->privateKeyName = generate_random_name();
        $this->remoteServerName = generate_random_name();
        if (isDev()) {
            $this->privateKey = '-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevAAAAJi/QySHv0Mk
hwAAAAtzc2gtZWQyNTUxOQAAACBbhpqHhqv6aI67Mj9abM3DVbmcfYhZAhC7ca4d9UCevA
AAAECBQw4jg1WRT2IGHMncCiZhURCts2s24HoDS0thHnnRKVuGmoeGq/pojrsyP1pszcNV
uZx9iFkCELtxrh31QJ68AAAAEXNhaWxANzZmZjY2ZDJlMmRkAQIDBA==
-----END OPENSSH PRIVATE KEY-----';
            $this->privateKeyDescription = 'Created by Coolify';
            $this->remoteServerDescription = 'Created by Coolify';
            $this->remoteServerHost = 'coolify-testing-host';
        }
    }
    public function explanation()
    {
        if (isCloud()) {
            return $this->setServerType('remote');
        }
        $this->currentState = 'select-server-type';
    }

    public function restartBoarding()
    {
        return redirect()->route('boarding');
    }
    public function skipBoarding()
    {
        Team::find(currentTeam()->id)->update([
            'show_boarding' => false
        ]);
        ray(currentTeam());
        refreshSession();
        return redirect()->route('dashboard');
    }

    public function setServerType(string $type)
    {
        $this->selectedServerType = $type;
        if ($this->selectedServerType === 'localhost') {
            $this->createdServer = Server::find(0);
            if (!$this->createdServer) {
                return $this->emit('error', 'Localhost server is not found. Something went wrong during installation. Please try to reinstall or contact support.');
            }
            $this->serverPublicKey = $this->createdServer->privateKey->publicKey();
            return $this->validateServer('localhost');
        } elseif ($this->selectedServerType === 'remote') {
            $this->privateKeys = PrivateKey::ownedByCurrentTeam(['name'])->where('id', '!=', 0)->get();
            if ($this->privateKeys->count() > 0) {
                $this->selectedExistingPrivateKey = $this->privateKeys->first()->id;
            }
            $this->servers = Server::ownedByCurrentTeam(['name'])->where('id', '!=', 0)->get();
            if ($this->servers->count() > 0) {
                $this->selectedExistingServer = $this->servers->first()->id;
                $this->currentState = 'select-existing-server';
                return;
            }
            $this->currentState = 'private-key';
        }
    }
    public function selectExistingServer()
    {
        $this->createdServer = Server::find($this->selectedExistingServer);
        if (!$this->createdServer) {
            $this->emit('error', 'Server is not found.');
            $this->currentState = 'private-key';
            return;
        }
        $this->selectedExistingPrivateKey = $this->createdServer->privateKey->id;
        $this->serverPublicKey = $this->createdServer->privateKey->publicKey();
        $this->validateServer();
    }
    public function getProxyType()
    {
        $proxyTypeSet = $this->createdServer->proxy->type;
        if (!$proxyTypeSet) {
            $this->currentState = 'select-proxy';
            return;
        }
        $this->getProjects();
    }
    public function selectExistingPrivateKey()
    {
        $this->createdPrivateKey = PrivateKey::find($this->selectedExistingPrivateKey);
        $this->privateKey = $this->createdPrivateKey->private_key;
        $this->currentState = 'create-server';
    }
    public function createNewServer()
    {
        $this->selectedExistingServer = null;
        $this->currentState = 'private-key';
    }
    public function setPrivateKey(string $type)
    {
        $this->selectedExistingPrivateKey = null;
        $this->privateKeyType = $type;
        if ($type === 'create') {
            $this->createNewPrivateKey();
        }
        $this->currentState = 'create-private-key';
    }
    public function savePrivateKey()
    {
        $this->validate([
            'privateKeyName' => 'required',
            'privateKey' => 'required',
        ]);
        $this->createdPrivateKey = PrivateKey::create([
            'name' => $this->privateKeyName,
            'description' => $this->privateKeyDescription,
            'private_key' => $this->privateKey,
            'team_id' => currentTeam()->id
        ]);
        $this->createdPrivateKey->save();
        $this->currentState = 'create-server';
    }
    public function saveServer()
    {
        $this->validate([
            'remoteServerName' => 'required',
            'remoteServerHost' => 'required',
            'remoteServerPort' => 'required|integer',
            'remoteServerUser' => 'required',
        ]);
        $this->privateKey = formatPrivateKey($this->privateKey);
        $foundServer = Server::whereIp($this->remoteServerHost)->first();
        if ($foundServer) {
            return $this->emit('error', 'IP address is already in use by another team.');
        }
        $this->createdServer = Server::create([
            'name' => $this->remoteServerName,
            'ip' => $this->remoteServerHost,
            'port' => $this->remoteServerPort,
            'user' => $this->remoteServerUser,
            'description' => $this->remoteServerDescription,
            'private_key_id' => $this->createdPrivateKey->id,
            'team_id' => currentTeam()->id,
        ]);
        $this->createdServer->save();
        $this->validateServer();
    }
    public function validateServer()
    {
        try {
            $customErrorMessage = "Server is not reachable:";
            config()->set('coolify.mux_enabled', false);

            instant_remote_process(['uptime'], $this->createdServer, true);

            $this->createdServer->settings()->update([
                'is_reachable' => true,
            ]);
        } catch (\Throwable $e) {
            $this->serverReachable = false;
            return handleError(error: $e, customErrorMessage: $customErrorMessage, livewire: $this);
        }

        try {
            $dockerVersion = instant_remote_process(["docker version|head -2|grep -i version| awk '{print $2}'"], $this->createdServer, true);
            $dockerVersion = checkMinimumDockerEngineVersion($dockerVersion);
            if (is_null($dockerVersion)) {
                $this->currentState = 'install-docker';
                throw new \Exception('Docker version is not supported or not installed.');
            }
            $this->createdServer->settings()->update([
                'is_usable' => true,
            ]);
            $this->getProxyType();
        } catch (\Throwable $e) {
            $this->dockerInstallationStarted = false;
            return handleError(error: $e, customErrorMessage: $customErrorMessage, livewire: $this);
        }
    }
    public function installDocker()
    {
        $this->dockerInstallationStarted = true;
        $activity = resolve(InstallDocker::class)($this->createdServer);
        $this->emit('newMonitorActivity', $activity->id);
    }
    public function dockerInstalledOrSkipped()
    {
        $this->validateServer();
    }
    public function selectProxy(string|null $proxyType = null)
    {
        if (!$proxyType) {
            return $this->getProjects();
        }
        $this->createdServer->proxy->type = $proxyType;
        $this->createdServer->proxy->status = 'exited';
        $this->createdServer->save();
        $this->getProjects();
    }

    public function getProjects()
    {
        $this->projects = Project::ownedByCurrentTeam(['name'])->get();
        if ($this->projects->count() > 0) {
            $this->selectedExistingProject = $this->projects->first()->id;
        }
        $this->currentState = 'create-project';
    }
    public function selectExistingProject()
    {
        $this->createdProject = Project::find($this->selectedExistingProject);
        $this->currentState = 'create-resource';
    }
    public function createNewProject()
    {
        $this->createdProject = Project::create([
            'name' => "My first project",
            'team_id' => currentTeam()->id
        ]);
        $this->currentState = 'create-resource';
    }
    public function showNewResource()
    {
        $this->skipBoarding();
        return redirect()->route(
            'project.resources.new',
            [
                'project_uuid' => $this->createdProject->uuid,
                'environment_name' => 'production',
                'server' => $this->createdServer->id,
            ]
        );
    }
    private function createNewPrivateKey()
    {
        $this->privateKeyName = generate_random_name();
        $this->privateKeyDescription = 'Created by Coolify';
        ['private' => $this->privateKey, 'public' => $this->publicKey] = generateSSHKey();
    }
    public function render()
    {
        return view('livewire.boarding.index')->layout('layouts.boarding');
    }
}
