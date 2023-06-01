<div x-init="$wire.loadImages">
    <div class="flex gap-2">
        <h2 class="pb-0">Rollback</h2>
        <x-forms.button isHighlighted wire:click='loadImages'>Reload Available Images</x-forms.button>
    </div>
    <div class="pb-4 text-sm">You can easily rollback to a previously built image quickly.</div>
    <div wire:loading wire:target='loadImages'>
        <x-loading />
    </div>
    <div wire:loading.remove wire:target='loadImages'>
        <div class="flex flex-wrap">
            @forelse ($images as $image)
                <div class="w-2/4 p-2">
                    <div class="rounded shadow-lg bg-coolgray-200">
                        <div class="p-2">
                            <div class="text-sm">
                                @if (data_get($image, 'is_current'))
                                    <span class="font-bold text-warning">LIVE</span>
                                    |
                                @endif
                                SHA: {{ data_get($image, 'tag') }}
                            </div>
                            <div class="text-xs">{{ data_get($image, 'created_at') }}</div>
                        </div>
                        <div class="flex justify-end p-2">
                            @if (data_get($image, 'is_current'))
                                <x-forms.button disabled tooltip="This image is currently running.">
                                    Rollback
                                </x-forms.button>
                            @else
                                <x-forms.button isHighlighted
                                    wire:click="rollbackImage('{{ data_get($image, 'tag') }}')">
                                    Rollback
                                </x-forms.button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div>No images found
                </div>
            @endforelse
        </div>
    </div>
</div>
