<?php

namespace App\Http\Livewire\Server;

use App\Models\Server;
use Livewire\Component;

class ShowPrivateKey extends Component
{
    public Server $server;
    public $privateKeys;
    public $parameters;

    public function setPrivateKey($newPrivateKeyId)
    {
        try {
            $oldPrivateKeyId = $this->server->private_key_id;
            $this->server->update([
                'private_key_id' => $newPrivateKeyId
            ]);
            $this->server->refresh();
            refresh_server_connection($this->server->privateKey);
            $this->checkConnection();
        } catch (\Exception $e) {
            $this->server->update([
                'private_key_id' => $oldPrivateKeyId
            ]);
            $this->server->refresh();
             refresh_server_connection($this->server->privateKey);
            return general_error_handler($e, that: $this);
        }
    }

    public function checkConnection()
    {
        try {
            ['uptime' => $uptime, 'dockerVersion' => $dockerVersion] = validateServer($this->server);
            if ($uptime) {
                $this->emit('success', 'Server is reachable with this private key.');
            } else {
                throw new \Exception('Server is not reachable with this private key.');
            }
            if ($dockerVersion) {
                $this->emit('success', 'Server is usable for Coolify.');
            } else {
                throw new \Exception('Old Docker version detected (lower than 23).');
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function mount()
    {
        $this->parameters = get_route_parameters();
    }
}
