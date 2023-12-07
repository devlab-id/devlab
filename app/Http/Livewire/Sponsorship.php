<?php

namespace App\Http\Livewire;

use Livewire\Component;

class Sponsorship extends Component
{
    public function getListeners()
    {
        $teamId = auth()->user()->currentTeam()->id;
        return [
            "echo-private:custom.{$teamId},TestEvent" => 'testEvent',
        ];
    }
    public function testEvent()
    {
        $this->emit('success', 'Realtime events configured!');
    }
    public function disable()
    {
        auth()->user()->update(['is_notification_sponsorship_enabled' => false]);
    }
    public function render()
    {
        return view('livewire.sponsorship');
    }
}
