<x-layout>
    <div class="flex gap-2">
        <h1>Projects</span></h1>
        @if ($servers > 0)
            <x-forms.button class="btn" onclick="newEmptyProject.showModal()">+ Add</x-forms.button>
            <livewire:project.add-empty/>
        @endif
    </div>
    <div class="pt-2 pb-10 ">All Projects</div>
    <div class="grid gap-2 lg:grid-cols-2">
        @if ($servers === 0)
            <div>
                <div>No servers found. Without a server, you won't be able to do much.</div>
                <x-use-magic-bar link="/server/new"/>
            </div>
        @else
            @forelse ($projects as $project)
                <div class="gap-2 border border-transparent cursor-pointer box group" x-data
                     x-on:click="goto('{{ $project->uuid }}')">
                    <div class="flex flex-col mx-6">
                        <a class=" group-hover:text-white hover:no-underline"
                           href="{{ route('project.show', ['project_uuid' => data_get($project, 'uuid')]) }}">{{ $project->name }}</a>
                        <div class="text-xs group-hover:text-white hover:no-underline"
                             href="{{ route('project.show', ['project_uuid' => data_get($project, 'uuid')]) }}">
                            {{ $project->description }}</div>
                    </div>
                    <div class="flex-1"></div>
                    <a class="mx-4 rounded hover:text-white"
                       href="{{ route('project.edit', ['project_uuid' => data_get($project, 'uuid')]) }}">
                        <svg
                            xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path
                                d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/>
                            <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/>
                        </svg>
                    </a>
                </div>
            @empty
                <div>
                    <div>No project found.</div>
                    <x-use-magic-bar/>
                </div>
            @endforelse
        @endif

        <script>
            function goto(uuid) {
                window.location.href = '/project/' + uuid;
            }
        </script>
    </div>
</x-layout>
