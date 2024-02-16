<div>
    @if ($server->id !== 0)
        <h2 class="pt-4">Danger Zone</h2>
        <div class="">Woah. I hope you know what are you doing.</div>
        <h4 class="pt-4">Delete Server</h4>
        <div class="pb-4">This will remove this server from Coolify. Beware! There is no coming
            back!
        </div>
        @if ($server->definedResources()->count() > 0)
            <x-new-modal disabled isErrorButton buttonTitle="Delete">
                This server will be deleted. It is not reversible. <br>Please think again.
            </x-new-modal>
        @else
            <x-new-modal isErrorButton buttonTitle="Delete">
                This server will be deleted. It is not reversible. <br>Please think again.
            </x-new-modal>
        @endif
        <div class="flex flex-col">
            @forelse ($server->definedResources() as $resource)
                @if ($loop->first)
                    <h3 class="pt-4">Resources</h3>
                @endif
                @if ($resource->link())
                    <a class="flex gap-2 p-1 hover:bg-coolgray-100 hover:no-underline" href="{{ $resource->link() }}">
                        <div class="w-40">
                            @if ($resource->type() === 'service')
                                <x-status.services :service="$resource" />
                            @else
                                <x-status.index :status="$resource->status" :showRefreshButton="false" :showIcon="false" />
                            @endif
                        </div>
                        <div>{{ $resource->name }}<span
                                class="px-2 text-xs">({{ str($resource->type())->headline() }})</span></div>
                    </a>
                @else
                    <div class="flex gap-2 p-1 hover:bg-coolgray-100 hover:no-underline">
                        <div class="w-40">
                            @if ($resource->type() === 'service')
                                <x-status.services :service="$resource" />
                            @else
                                <x-status.index :status="$resource->status" :showRefreshButton="false" :showIcon="false" />
                            @endif
                        </div>
                        <div>{{ $resource->name }}<span
                                class="px-2 text-xs">({{ str($resource->type())->headline() }})</span></div>
                    </div>
                @endif
            @empty
            @endforelse
        </div>
    @else

    @endif
</div>
