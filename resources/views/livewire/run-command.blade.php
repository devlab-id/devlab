<div>
    <h1>Command Center</h1>
    <form class="flex items-end justify-center gap-2" wire:submit.prevent='runCommand'>
        <x-inputs.input placeholder="ls -l" autofocus noDirty noLabel id="command" label="Command" required />
        <select wire:model.defer="server">
            @foreach ($servers as $server)
                @if ($loop->first)
                    <option selected value="{{ $server->uuid }}">{{ $server->name }}</option>
                @else
                    <option value="{{ $server->uuid }}">{{ $server->name }}</option>
                @endif
            @endforeach
        </select>
        <x-inputs.button class="btn-xl" type="submit">Run</x-inputs.button>
    </form>
    <div class="container w-full pt-10 mx-auto">
        <livewire:activity-monitor />
    </div>
</div>
