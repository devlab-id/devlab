<?php

namespace App\Livewire\Team;

use App\Models\TeamInvitation;
use Livewire\Component;

class Invitations extends Component
{
    public $invitations;
    protected $listeners = ['refreshInvitations'];

    public function deleteInvitation(int $invitation_id)
    {
        TeamInvitation::find($invitation_id)->delete();
        $this->refreshInvitations();
        $this->dispatch('success', 'Invitation revoked.');
    }

    public function refreshInvitations()
    {
        $this->invitations = TeamInvitation::whereTeamId(currentTeam()->id)->get();
    }
}
