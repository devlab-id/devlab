<?php

namespace App\Http\Livewire\Server;

use App\Models\Server;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests;
    public ?Server $server = null;
    public $parameters = [];
    public function mount()
    {
        $this->parameters = get_route_parameters();
        try {
            $this->server = Server::ownedByCurrentTeam(['name', 'description', 'ip', 'port', 'user', 'proxy'])->whereUuid(request()->server_uuid)->first();
            if (is_null($this->server)) {
                return redirect()->route('server.all');
            }
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
    public function submit()
    {
        $this->emit('serverRefresh');
    }
    public function render()
    {
        return view('livewire.server.show');
    }
}
