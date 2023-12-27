<div class="pb-6">
    <livewire:server.proxy.modal :server="$server" />
    <div class="flex items-center gap-2">
        <h1>Server</h1>
        @if ($server->proxyType() !== 'NONE' && $server->isFunctional() && !$server->isSwarmWorker())
            <livewire:server.proxy.status :server="$server" />
        @endif
    </div>
    <div class="subtitle ">{{ data_get($server, 'name') }}</div>
    <nav class="navbar-main">
        <a  class="{{ request()->routeIs('server.show') ? 'text-white' : '' }}"
            href="{{ route('server.show', [
                'server_uuid' => data_get($parameters, 'server_uuid'),
            ]) }}">
            <button>General</button>
        </a>
        <a  class="{{ request()->routeIs('server.private-key') ? 'text-white' : '' }}"
            href="{{ route('server.private-key', [
                'server_uuid' => data_get($parameters, 'server_uuid'),
            ]) }}">
            <button>Private Key</button>
        </a>
        @if (!$server->isSwarmWorker())
            <a  class="{{ request()->routeIs('server.proxy') ? 'text-white' : '' }}"
                href="{{ route('server.proxy', [
                    'server_uuid' => data_get($parameters, 'server_uuid'),
                ]) }}">
                <button>Proxy</button>
            </a>
            <a  class="{{ request()->routeIs('server.destinations') ? 'text-white' : '' }}"
                href="{{ route('server.destinations', [
                    'server_uuid' => data_get($parameters, 'server_uuid'),
                ]) }}">
                <button>Destinations</button>
            </a>
            <a  class="{{ request()->routeIs('server.log-drains') ? 'text-white' : '' }}"
                href="{{ route('server.log-drains', [
                    'server_uuid' => data_get($parameters, 'server_uuid'),
                ]) }}">
                <button>Log Drains</button>
            </a>
        @endif

        <div class="flex-1"></div>
        @if ($server->proxyType() !== 'NONE' && $server->isFunctional() && !$server->isSwarmWorker())
            <livewire:server.proxy.deploy :server="$server" />
        @endif
    </nav>
</div>
