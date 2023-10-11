<?php

namespace App\Http\Livewire\Subscription;

use App\Models\InstanceSettings;
use Livewire\Component;

class Show extends Component
{
    public InstanceSettings $settings;
    public bool $alreadySubscribed = false;
    public function mount() {
        if (!isCloud()) {
            return redirect('/');
        }
        $this->settings = InstanceSettings::get();
        $this->alreadySubscribed = currentTeam()->subscription()->exists();
    }
    public function stripeCustomerPortal() {
        $session = getStripeCustomerPortalSession(currentTeam());
        if (is_null($session)) {
            return;
        }
        return redirect($session->url);
    }
    public function render()
    {
        return view('livewire.subscription.show')->layout('layouts.subscription');
    }
}
