<?php

namespace App\Http\Livewire\Boarding;

use App\Actions\Server\InstallDocker;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Support\Collection;
use Livewire\Component;
use Visus\Cuid2\Cuid2;

class Index extends Component
{
    public string $currentState = 'welcome';

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
        if ($this->createdServer) {
            $this->createdServer->delete();
        }
        if ($this->createdPrivateKey) {
            $this->createdPrivateKey->delete();
        }
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
        if ($type === 'localhost') {
            $this->createdServer = Server::find(0);
            if (!$this->createdServer) {
                return $this->emit('error', 'Localhost server is not found. Something went wrong during installation. Please try to reinstall or contact support.');
            }
            return $this->validateServer('localhost');
        } elseif ($type === 'remote') {
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
        $this->validateServer();
        $this->getProxyType();
        $this->getProjects();
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
        $this->currentState = 'create-server';
    }
    public function saveServer()
    {
        $this->validate([
            'remoteServerName' => 'required',
            'remoteServerHost' => 'required|ip',
            'remoteServerPort' => 'required|integer',
            'remoteServerUser' => 'required',
        ]);
        $this->privateKey = formatPrivateKey($this->privateKey);
        $this->createdPrivateKey = new PrivateKey();
        $this->createdPrivateKey->private_key = $this->privateKey;
        $this->createdPrivateKey->name = $this->privateKeyName;
        $this->createdPrivateKey->description = $this->privateKeyDescription;
        $this->createdPrivateKey->team_id = currentTeam()->id;
        $foundServer = Server::whereIp($this->remoteServerHost)->first();
        if ($foundServer) {
            return $this->emit('error', 'IP address is already in use by another team.');
        }
        $this->createdServer = new Server();
        $this->createdServer->uuid = (string)new Cuid2(7);
        $this->createdServer->name = $this->remoteServerName;
        $this->createdServer->ip = $this->remoteServerHost;
        $this->createdServer->port = $this->remoteServerPort;
        $this->createdServer->user = $this->remoteServerUser;
        $this->createdServer->description = $this->remoteServerDescription;
        $this->createdServer->privateKey = $this->createdPrivateKey;
        $this->createdServer->team_id = currentTeam()->id;

        ray($this->createdServer);


        $this->validateServer();
    }
    public function validateServer(?string $type = null)
    {
        try {
            $customErrorMessage = "Server is not reachable:";
            config()->set('coolify.mux_enabled', false);
            instant_remote_process(['uptime'], $this->createdServer, true);
            $dockerVersion = instant_remote_process(["docker version|head -2|grep -i version| awk '{print $2}'"], $this->createdServer, true);
            $dockerVersion = checkMinimumDockerEngineVersion($dockerVersion);
            if (is_null($dockerVersion)) {
                throw new \Exception('No Docker Engine or older than 23 version installed.');
            }
            $customErrorMessage = "Cannot create Server or Private Key. Please try again.";
            if ($type !== 'localhost') {
                $createdPrivateKey = PrivateKey::create([
                    'name' => $this->privateKeyName,
                    'description' => $this->privateKeyDescription,
                    'private_key' => $this->privateKey,
                    'team_id' => currentTeam()->id
                ]);
                $server = Server::create([
                    'name' => $this->remoteServerName,
                    'ip' => $this->remoteServerHost,
                    'port' => $this->remoteServerPort,
                    'user' => $this->remoteServerUser,
                    'description' => $this->remoteServerDescription,
                    'private_key_id' => $createdPrivateKey->id,
                    'team_id' => currentTeam()->id,
                ]);
                $server->settings->is_reachable = true;
                $server->settings->is_usable = true;
                $server->settings->save();
            } else {
                $this->createdServer->settings->update([
                    'is_reachable' => true,
                ]);
            }
            $this->getProxyType();
        } catch (\Throwable $e) {
            return handleError(error: $e, customErrorMessage: $customErrorMessage, livewire: $this);
        }
    }
    public function installDocker()
    {
        $activity = resolve(InstallDocker::class)($this->createdServer, currentTeam());
        $this->emit('newMonitorActivity', $activity->id);
        $this->currentState = 'select-proxy';
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
