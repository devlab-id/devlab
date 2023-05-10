<x-layout>
    @if ($type === 'project')
        <h1>New Project</h1>
    @elseif ($type === 'resource')
        <h1>New Resource</h1>
    @endif
    <div id="vue">
        <magic-search-bar></magic-search-bar>
    </div>
    <div x-data="{ activeTab: 'choose' }">
        <div class="flex flex-col w-64 gap-2 mb-10">
            <x-inputs.button @click.prevent="activeTab = 'public-repo'">Public Repository</x-inputs.button>
            <x-inputs.button @click.prevent="activeTab = 'github-private-repo'">Private Repository (with GitHub App)
            </x-inputs.button>
            <x-inputs.button @click.prevent="activeTab = 'github-private-repo-deploy-key'">Private Repository (with
                Deploy Key)
            </x-inputs.button>
            @if ($type === 'project')
                <livewire:project.new.empty-project />
            @endif
        </div>

        <div x-cloak x-show="activeTab === 'public-repo'">
            <livewire:project.new.public-git-repository :type="$type" />
        </div>
        <div x-cloak x-show="activeTab === 'github-private-repo'">
            <livewire:project.new.github-private-repository :type="$type" />
        </div>
        <div x-cloak x-show="activeTab === 'github-private-repo-deploy-key'">
            <livewire:project.new.github-private-repository-deploy-key :type="$type" />
        </div>
    </div>
</x-layout>
