<?php

namespace App\Livewire\Profile;

use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    public int $userId;
    public string $email;

    #[Validate('required')]
    public string $name;

    public function mount()
    {
        $this->userId = auth()->user()->id;
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
    }

    public function submit()

    {
        try {
            $this->validate();
            auth()->user()->update([
                'name' => $this->name,
            ]);

            $this->dispatch('success', 'Profile updated successfully.');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
