<?php

namespace App\Http\Livewire\PrivateKey;

use App\Models\PrivateKey;
use Livewire\Component;
use phpseclib3\Crypt\PublicKeyLoader;

class Create extends Component
{
    public ?string $from = null;
    public string $name;
    public ?string $description = null;
    public string $value;
    public ?string $publicKey = null;
    protected $rules = [
        'name' => 'required|string',
        'value' => 'required|string',
    ];
    protected $validationAttributes = [
        'name' => 'name',
        'value' => 'private Key',
    ];

    public function generateNewKey()
    {
        $this->name = generate_random_name();
        $this->description = 'Created by Coolify';
        ['private' => $this->value, 'public' => $this->publicKey] = generateSSHKey();
    }
    public function updated($updateProperty)
    {
        if ($updateProperty === 'value') {
            try {
                $this->publicKey = PublicKeyLoader::load($this->$updateProperty)->getPublicKey()->toString('OpenSSH',['comment' => '']);
            } catch (\Throwable $e) {
                $this->publicKey = "Invalid private key";
            }
        }
        $this->validateOnly($updateProperty);
    }
    public function createPrivateKey()
    {
        $this->validate();
        try {
            $this->value = trim($this->value);
            if (!str_ends_with($this->value, "\n")) {
                $this->value .= "\n";
            }
            $private_key = PrivateKey::create([
                'name' => $this->name,
                'description' => $this->description,
                'private_key' => $this->value,
                'team_id' => currentTeam()->id
            ]);
            if ($this->from === 'server') {
                return redirect()->route('server.create');
            }
            return redirect()->route('security.private-key.show', ['private_key_uuid' => $private_key->uuid]);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
