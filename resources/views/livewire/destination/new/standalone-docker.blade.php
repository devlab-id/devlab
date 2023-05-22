<div>
    <form class="flex flex-col gap-4" wire:submit.prevent='submit'>
        <div class="flex gap-2">
            <h1>New Destination</h1>
            <x-inputs.button type="submit">
                Save
            </x-inputs.button>
        </div>
        <x-inputs.input id="name" label="Name" required />
        <x-inputs.input id="network" label="Network" required />
        <x-inputs.select id="server_id" label="Select a server" required>
            @foreach ($servers as $server)
                <option disabled>Select a server</option>
                <option value="{{ $server->id }}">{{ $server->name }}</option>
            @endforeach
        </x-inputs.select>

    </form>
</div>
