<div x-data="{ activeTab: window.location.hash ? window.location.hash.substring(1) : 'service-stack' }" x-init="$wire.check_status" wire:poll.5000ms="check_status">
    <livewire:project.service.navbar :service="$service" :parameters="$parameters" :query="$query" />
    <div class="flex h-full pt-6">
        <div class="flex flex-col items-start gap-4 min-w-fit">
            <a target="_blank" href="{{ $service->documentation() }}">Documentation <x-external-link /></a>
            <a :class="activeTab === 'service-stack' && 'dark:text-white'"
                @click.prevent="activeTab = 'service-stack';
                window.location.hash = 'service-stack'"
                href="#">Service Stack</a>
            <a :class="activeTab === 'environment-variables' && 'dark:text-white'"
                @click.prevent="activeTab = 'environment-variables'; window.location.hash = 'environment-variables'"
                href="#">Environment
                Variables</a>
            <a :class="activeTab === 'storages' && 'dark:text-white'"
                @click.prevent="activeTab = 'storages';
                window.location.hash = 'storages'"
                href="#">Storages</a>
            <a :class="activeTab === 'execute-command' && 'dark:text-white'"
                @click.prevent="activeTab = 'execute-command';
                window.location.hash = 'execute-command'"
                href="#">Execute Command</a>
            <a :class="activeTab === 'logs' && 'dark:text-white'"
                @click.prevent="activeTab = 'logs';
                window.location.hash = 'logs'"
                href="#">Logs</a>
            <a :class="activeTab === 'webhooks' && 'dark:text-white'"
                @click.prevent="activeTab = 'webhooks'; window.location.hash = 'webhooks'" href="#">Webhooks
            </a>
            <a :class="activeTab === 'resource-operations' && 'dark:text-white'"
                @click.prevent="activeTab = 'resource-operations'; window.location.hash = 'resource-operations'"
                href="#">Resource Operations
            </a>
            <a :class="activeTab === 'tags' && 'dark:text-white'"
                @click.prevent="activeTab = 'tags'; window.location.hash = 'tags'" href="#">Tags
            </a>
            <a :class="activeTab === 'danger' && 'dark:text-white'"
                @click.prevent="activeTab = 'danger';
                window.location.hash = 'danger'"
                href="#">Danger Zone
            </a>
        </div>
        <div class="w-full pl-8">
            <div x-cloak x-show="activeTab === 'service-stack'">
                <livewire:project.service.stack-form :service="$service" />
                <div class="grid grid-cols-1 gap-2 pt-4 xl:grid-cols-1">
                    @foreach ($applications as $application)
                        <div @class([
                            'border-l border-dashed border-red-500' => Str::of(
                                $application->status)->contains(['exited']),
                            'border-l border-dashed border-success' => Str::of(
                                $application->status)->contains(['running']),
                            'border-l border-dashed border-warning' => Str::of(
                                $application->status)->contains(['starting']),
                            'flex gap-2 box-without-bg bg-coolgray-100 hover:text-neutral-300 group',
                        ])>
                            <div class="flex flex-row w-full">
                                <div class="flex flex-col flex-1">
                                    <div class="pb-2">
                                        @if ($application->human_name)
                                            {{ Str::headline($application->human_name) }}
                                        @else
                                            {{ Str::headline($application->name) }}
                                        @endif
                                        <span class="text-xs">({{ $application->image }})</span>
                                    </div>
                                    @if ($application->configuration_required)
                                        <span class="text-xs text-error">(configuration required)</span>
                                    @endif
                                    @if ($application->description)
                                        <span class="text-xs">{{ Str::limit($application->description, 60) }}</span>
                                    @endif
                                    @if ($application->fqdn)
                                        <span class="text-xs">{{ Str::limit($application->fqdn, 60) }}</span>
                                    @endif
                                    <div class="text-xs">{{ $application->status }}</div>
                                </div>
                                <div class="flex items-center px-4">
                                    <a class="mx-4 font-bold hover:underline"
                                        href="{{ route('project.service.index', [...$parameters, 'stack_service_uuid' => $application->uuid]) }}">
                                        Settings
                                    </a>
                                    <x-modal-confirmation action="restartApplication({{ $application->id }})"
                                        isErrorButton buttonTitle="Restart">
                                        This application will be unavailable during the restart. <br>Please think again.
                                    </x-modal-confirmation>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @foreach ($databases as $database)
                        <div @class([
                            'border-l border-dashed border-red-500' => Str::of(
                                $database->status)->contains(['exited']),
                            'border-l border-dashed border-success' => Str::of(
                                $database->status)->contains(['running']),
                            'border-l border-dashed border-warning' => Str::of(
                                $database->status)->contains(['restarting']),
                            'flex gap-2 box-without-bg bg-coolgray-100 hover:text-neutral-300 group',
                        ])>
                            <div class="flex flex-row w-full">
                                <div class="flex flex-col flex-1">
                                    <div class="pb-2">
                                        @if ($database->human_name)
                                            {{ Str::headline($database->human_name) }}
                                        @else
                                            {{ Str::headline($database->name) }}
                                        @endif
                                        <span class="text-xs">({{ $database->image }})</span>
                                    </div>
                                    @if ($database->configuration_required)
                                        <span class="text-xs text-error">(configuration required)</span>
                                    @endif
                                    @if ($database->description)
                                        <span class="text-xs">{{ Str::limit($database->description, 60) }}</span>
                                    @endif
                                    <div class="text-xs">{{ $database->status }}</div>
                                </div>
                                <div class="flex items-center px-4">
                                    <a class="mx-4 font-bold hover:underline"
                                        href="{{ route('project.service.index', [...$parameters, 'stack_service_uuid' => $database->uuid]) }}">
                                        Settings
                                    </a>
                                    <x-modal-confirmation action="restartDatabase({{ $database->id }})"
                                        isErrorButton buttonTitle="Restart">
                                        This database will be unavailable during the restart. <br>Please think again.
                                    </x-modal-confirmation>

                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div x-cloak x-show="activeTab === 'storages'">
                <div class="flex items-center gap-2">
                    <h2>Storages</h2>
                </div>
                <div class="pb-4">Persistent storage to preserve data between deployments.</div>
                <span class="dark:text-warning">Please modify storage layout in your Docker Compose file.</span>
                @foreach ($applications as $application)
                    <livewire:project.service.storage wire:key="application-{{ $application->id }}"
                        :resource="$application" />
                @endforeach
                @foreach ($databases as $database)
                    <livewire:project.service.storage wire:key="database-{{ $database->id }}" :resource="$database" />
                @endforeach
            </div>
            <div x-cloak x-show="activeTab === 'webhooks'">
                <livewire:project.shared.webhooks :resource="$service" />
            </div>
            <div x-cloak x-show="activeTab === 'logs'">
                <livewire:project.shared.logs :resource="$service" />
            </div>
            <div x-cloak x-show="activeTab === 'execute-command'">
                <livewire:project.shared.execute-container-command :resource="$service" />
            </div>
            <div x-cloak x-show="activeTab === 'environment-variables'">
                <livewire:project.shared.environment-variable.all :resource="$service" />
            </div>
            <div x-cloak x-show="activeTab === 'resource-operations'">
                <livewire:project.shared.resource-operations :resource="$service" />
            </div>
            <div x-cloak x-show="activeTab === 'tags'">
                <livewire:project.shared.tags :resource="$service" />
            </div>
            <div x-cloak x-show="activeTab === 'danger'">
                <livewire:project.shared.danger :resource="$service" />
            </div>
        </div>
    </div>
</div>
