<x-layout>
    <x-team.navbar :team="session('currentTeam')" />
    <div class="flex items-start gap-2">
        <h2 class="pb-4">S3 Storages</h2>
        <x-forms.button class="btn">
            <a class="text-white hover:no-underline" href="/team/storages/new">+ Add</a>
        </x-forms.button>
    </div>
    <div class="grid gap-2 lg:grid-cols-2">
        @forelse ($s3 as $storage)
            <div x-data x-on:click="goto('{{ $storage->uuid }}')" @class(['gap-2 border cursor-pointer box group border-transparent'])>
                <div class="flex flex-col mx-6">
                    <div class=" group-hover:text-white">
                        {{ $storage->name }}
                    </div>
                    <div class="text-xs group-hover:text-white">
                        {{ $storage->description }}</div>
                </div>
            </div>
        @empty
            <div>
                <div>No storage found.</div>
                <x-use-magic-bar link="/team/storages/new" />
            </div>
        @endforelse
    </div>
    <script>
        function goto(uuid) {
            window.location.href = '/team/storages/' + uuid;
        }
    </script>
</x-layout>
