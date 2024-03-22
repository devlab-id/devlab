@php use App\Actions\CoolifyTask\RunRemoteProcess; @endphp
<div>
    @if ($this->activity)
        @if (isset($header))
            <div class="flex gap-2 pb-2">
                <h3>{{ $header }}</h3>
                @if ($isPollingActive)
                    <x-loading />
                @endif
            </div>
        @endif
        <div
            class="flex flex-col-reverse w-full px-4 py-2 overflow-y-auto text-white border border-solid rounded bg-coolgray-100 scrollbar border-coolgray-300 max-h-96">
            <pre class="font-mono whitespace-pre-wrap" @if ($isPollingActive) wire:poll.1000ms="polling" @endif>{{ RunRemoteProcess::decodeOutput($this->activity) }}</pre>
        </div>
    @else
        @if ($showWaiting)
            <x-loading text="Waiting..." />
        @endif
    @endif
</div>
