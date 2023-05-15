<div>
    <div class="flex items-center gap-2">
        <h2>Proxy</h2>
        <div>{{ $this->server->extra_attributes->proxy_status }}</div>
    </div>
    @if ($this->server->extra_attributes->proxy_status !== 'running')
        <select wire:model="selectedProxy">
            <option value="{{ \App\Enums\ProxyTypes::TRAEFIK_V2 }}">
                {{ \App\Enums\ProxyTypes::TRAEFIK_V2 }}
            </option>
        </select>
        <x-inputs.button isBold wire:click="setProxy">Set Proxy</x-inputs.button>
    @endif
    @if ($this->server->extra_attributes->proxy_type)
        <div wire:poll="proxyStatus">
            @if (
                $this->server->extra_attributes->last_applied_proxy_settings &&
                    $this->server->extra_attributes->last_saved_proxy_settings !==
                        $this->server->extra_attributes->last_applied_proxy_settings)
                <div class="text-red-500">Configuration out of sync.</div>
            @endif
            @if ($this->server->extra_attributes->proxy_status !== 'running')
                <x-inputs.button isBold wire:click="installProxy">
                    Install
                </x-inputs.button>
            @endif
            <x-inputs.button isBold wire:click="stopProxy">Stop</x-inputs.button>
            <span x-data="{ showConfiguration: false }">
                <x-inputs.button isBold x-on:click="showConfiguration = !showConfiguration">Show Configuration
                </x-inputs.button>
                <div class="pt-4">
                    <livewire:activity-monitor />
                </div>
                <template x-if="showConfiguration">
                    <div x-init="$wire.checkProxySettingsInSync" class="pt-4">
                        <h1>Proxy Configuration</h1>
                        <div wire:loading wire:target="checkProxySettingsInSync">
                            <x-proxy.loading />
                        </div>
                        @isset($this->proxy_settings)
                            <form wire:submit.prevent='saveConfiguration'>
                                <x-inputs.button isBold>Save</x-inputs.button>
                                <x-inputs.button x-on:click="showConfiguration = false" isBold
                                    wire:click.prevent="installProxy">
                                    Apply
                                </x-inputs.button>
                                <textarea wire:model.defer="proxy_settings" class="w-full" rows="30"></textarea>
                            </form>
                        @endisset
                    </div>
                </template>
            </span>
        </div>
    @endif
</div>
