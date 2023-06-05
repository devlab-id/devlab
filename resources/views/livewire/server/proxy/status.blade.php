<div>
    @if ($server->settings->is_validated)
        <div wire:poll.5000ms="proxyStatus">
            @if ($server->extra_attributes->proxy_status === 'running')
                <x-loading wire:loading.delay.longer />
                <div class="flex items-center gap-2 text-sm" wire:loading.remove.delay.longer>
                    <div class="text-xs font-medium tracking-wide text-white badge border-success">Running</div>
                </div>
            @else
                <x-loading wire:loading.delay.longer />
                <div class="flex items-center gap-2 text-sm" wire:loading.remove.delay.longer>
                    <div class="text-xs font-medium tracking-wide text-white badge border-error">Stopped</div>
                </div>
            @endif
        </div>
    @endif
</div>
