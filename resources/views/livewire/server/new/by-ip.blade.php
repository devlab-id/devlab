<div>
    <form class="flex flex-col gap-1" wire:submit.prevent='submit'>
        <div class="flex items-center gap-2">
            <h1>New Server</h1>
            <x-inputs.button type="submit">
                Save
            </x-inputs.button>
        </div>
        <x-inputs.input id="name" label="Name" required />
        <x-inputs.input id="description" label="Description" />
        <x-inputs.input id="ip" label="IP Address" required />
        <x-inputs.input id="user" label="User" />
        <x-inputs.input type="number" id="port" label="Port" />
        <x-inputs.input id="private_key_id" label="Private Key Id" readonly hidden />

        @if ($private_keys->count() > 0)
            <h1>Select a private key</h1>
            @foreach ($private_keys as $key)
                <div class="box" :class="{ 'bg-coollabs': {{ $private_key_id === $key->id }} }"
                    wire:click.defer.prevent="setPrivateKey('{{ $key->id }}')">
                    {{ $key->name }}
                </div>
            @endforeach
        @endif
    </form>
    @if ($private_keys->count() > 0)
        <h2>Or add a new private key</h2>
    @else
        <h2>Create private key</h2>
    @endif
    <livewire:private-key.create />
</div>
