<x-layout>
    <x-team.navbar :team="session('currentTeam')"/>
    <h2 class="pb-4">Notifications</h2>
    <div x-data="{ activeTab: window.location.hash ? window.location.hash.substring(1) : 'email' }" class="flex h-full">
        <div class="flex flex-col gap-4 min-w-fit">
            <a :class="activeTab === 'email' && 'text-white'"
               @click.prevent="activeTab = 'email'; window.location.hash = 'email'" href="#">Email</a>
            <a :class="activeTab === 'discord' && 'text-white'"
               @click.prevent="activeTab = 'discord'; window.location.hash = 'discord'" href="#">Discord</a>
        </div>
        <div class="w-full pl-8">
            <div x-cloak x-show="activeTab === 'email'" class="h-full">
                <livewire:notifications.email-settings :model="session('currentTeam')"/>
            </div>
            <div x-cloak x-show="activeTab === 'discord'">
                <livewire:notifications.discord-settings :model="session('currentTeam')"/>
            </div>
        </div>
</x-layout>
