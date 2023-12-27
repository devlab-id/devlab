<div>
    @if (session('error'))
        <span x-data x-init="$wire.emit('error', '{{ session('error') }}')" />
    @endif
    <h1>Dashboard</h1>
    <div class="subtitle">Your self-hosted environment</div>
    @if (request()->query->get('success'))
        <div class="rounded alert alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current shrink-0" fill="none"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Your subscription has been activated! Welcome onboard!</span>
        </div>
    @endif
    @if ($projects->count() === 0 && $servers->count() === 0)
        No resources found. Add your first server / private key <a class="text-white underline"
            href="{{ route('server.create') }}">here</a>.
    @endif
    @if ($projects->count() > 0)
        <h3 class="pb-4">Projects</h3>
    @endif
    @if ($projects->count() === 1)
        <div class="grid grid-cols-1 gap-2">
        @else
            <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
    @endif
    @foreach ($projects as $project)
        <div class="gap-2 border border-transparent cursor-pointer box group">
            @if (data_get($project, 'environments.0.name'))
                <a  class="flex flex-col flex-1 mx-6 hover:no-underline"
                    href="{{ route('project.resources', ['project_uuid' => data_get($project, 'uuid'), 'environment_name' => data_get($project, 'environments.0.name', 'production')]) }}">
                    <div class="font-bold text-white">{{ $project->name }}</div>
                    <div class="description">
                        {{ $project->description }}</div>
                </a>
            @else
                <a  class="flex flex-col flex-1 mx-6 hover:no-underline"
                    href="{{ route('project.show', ['project_uuid' => data_get($project, 'uuid')]) }}">
                    <div class="font-bold text-white">{{ $project->name }}</div>
                    <div class="description">
                        {{ $project->description }}</div>
                </a>
            @endif
            <div class="flex items-center">
                <a  class="mx-4 rounded group-hover:text-white hover:no-underline"
                    href="{{ route('project.resources.new', ['project_uuid' => data_get($project, 'uuid'), 'environment_name' => data_get($project, 'environments.0.name', 'production')]) }}">
                    <span class="font-bold hover:text-warning">+ New Resource</span>
                </a>
                <a  class="mx-4 rounded group-hover:text-white"
                    href="{{ route('project.edit', ['project_uuid' => data_get($project, 'uuid')]) }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon hover:text-warning" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" />
                        <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
                    </svg>
                </a>
            </div>
        </div>
    @endforeach
</div>
@if ($projects->count() > 0)
    <h3 class="py-4">Servers</h3>
@endif
@if ($servers->count() === 1)
    <div class="grid grid-cols-1 gap-2">
    @else
        <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
@endif
@foreach ($servers as $server)
    <a  href="{{ route('server.show', ['server_uuid' => data_get($server, 'uuid')]) }}"
        @class([
            'gap-2 border cursor-pointer box group',
            'border-transparent' => $server->settings->is_reachable,
            'border-red-500' => !$server->settings->is_reachable,
        ])>
        <div class="flex flex-col mx-6">
            <div class="font-bold text-white">
                {{ $server->name }}
            </div>
            <div class="description">
                {{ $server->description }}</div>
            <div class="flex gap-1 text-xs text-error">
                @if (!$server->settings->is_reachable)
                    <span>Not reachable</span>
                @endif
                @if (!$server->settings->is_reachable && !$server->settings->is_usable)
                    &
                @endif
                @if (!$server->settings->is_usable)
                    <span>Not usable by Coolify</span>
                @endif
            </div>
        </div>
        <div class="flex-1"></div>
    </a>
@endforeach
</div>
<script>
    function gotoProject(uuid, environment = 'production') {
        window.location.href = '/project/' + uuid + '/' + environment;
    }
</script>
{{-- <x-forms.button wire:click='getIptables'>Get IPTABLES</x-forms.button> --}}
</div>
