<x-layout>
    <h1>Server</h1>
    <livewire:server.form :server_id="$server->id" />
    <h2>Destinations <a href="{{ route('destination.new') }}"><button>New</button></h2>
    @if ($server->standaloneDockers)
        @foreach ($server->standaloneDockers as $docker)
            <p>Network: {{ data_get($docker, 'network') }}</p>
        @endforeach
    @endif
</x-layout>
