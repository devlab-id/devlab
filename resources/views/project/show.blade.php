<x-layout>
    <div class="flex items-center gap-2">
        <h1 class="pb-0">Environments</h1>
        <livewire:project.delete-project :project_id="$project->id" :resource_count="$project->applications->count()" />
    </div>
    <div class="pb-10 text-sm breadcrumbs">
        <ul>
            <li>{{ $project->name }} </li>
        </ul>
    </div>
    <div class="flex flex-col gap-2">
        @forelse ($project->environments as $environment)
            <a class="box" href="{{ route('project.resources', [$project->uuid, $environment->name]) }}">
                {{ $environment->name }}
            </a>
        @empty
            <p>No environments found.</p>
        @endforelse
    </div>
</x-layout>
