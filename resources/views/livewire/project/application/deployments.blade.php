<div class="flex flex-col gap-2 pb-10" @if ($skip == 0) wire:poll.5000ms='reload_deployments' @endif>
    <div class="flex items-end gap-2 pt-4">
        <h2>Deployments <span class="text-xs">({{ $deployments_count }})</span></h2>
        @if ($show_next)
            <x-forms.button wire:click="load_deployments({{ $default_take }})">Next Page
            </x-forms.button>
        @endif

    </div>
    <form wire:submit="filter" class="flex items-end gap-2">
        <x-forms.input id="pull_request_id" label="Pull Request"></x-forms.input>
        <x-forms.button type="submit">Filter</x-forms.button>
    </form>
    @forelse ($deployments as $deployment)
        <a @class([
            'bg-coolgray-200 p-2 border-l border-dashed transition-colors hover:no-underline',
            'cursor-not-allowed hover:bg-coolgray-200' =>
                data_get($deployment, 'status') === 'queued' ||
                data_get($deployment, 'status') === 'cancelled by system',
            'border-warning hover:bg-warning hover:text-black' =>
                data_get($deployment, 'status') === 'in_progress',
            'border-error hover:bg-error' =>
                data_get($deployment, 'status') === 'error',
            'border-success hover:bg-success' =>
                data_get($deployment, 'status') === 'finished',
        ]) @if (data_get($deployment, 'status') !== 'cancelled by system' && data_get($deployment, 'status') !== 'queued')
            href="{{ $current_url . '/' . data_get($deployment, 'deployment_uuid') }}"
    @endif
    class="hover:no-underline">
    <div class="flex flex-col justify-start">
        <div>
            {{ $deployment->created_at }} UTC
            <span class=" text-warning">></span>
            {{ $deployment->status }}
            @if (data_get($deployment, 'pull_request_id'))
                <span class=" text-warning">></span>
                Pull Request #{{ data_get($deployment, 'pull_request_id') }}
                @if (data_get($deployment, 'is_webhook'))
                    (Webhook)
                @endif
            @elseif (data_get($deployment, 'is_webhook'))
                <span class=" text-warning">></span>
        </div>
        Webhook (sha
        @if (data_get($deployment, 'commit'))
            {{ data_get($deployment, 'commit') }})
        @else
            HEAD)
        @endif
        @endif
    </div>

    <div class="flex flex-col" x-data="elapsedTime('{{ $deployment->deployment_uuid }}', '{{ $deployment->status }}', '{{ $deployment->created_at }}', '{{ $deployment->updated_at }}')">
        <div>
            @if ($deployment->status !== 'in_progress')
                Finished <span x-text="measure_since_started()">0s</span> in
            @else
                Running for
            @endif
            <span class="font-bold" x-text="measure_finished_time()">0s</span>
        </div>
    </div>
</div>
</a>
@empty
<div class="">No deployments found</div>
@endforelse
<script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1/plugin/utc.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dayjs@1/plugin/relativeTime.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        let timers = {};

        dayjs.extend(window.dayjs_plugin_utc);
        dayjs.extend(window.dayjs_plugin_relativeTime);

        Alpine.data('elapsedTime', (uuid, status, created_at, updated_at) => ({
            finished_time: 'calculating...',
            started_time: 'calculating...',
            init() {
                if (timers[uuid]) {
                    clearInterval(timers[uuid]);
                }
                if (status === 'in_progress') {
                    timers[uuid] = setInterval(() => {
                        this.finished_time = dayjs().diff(dayjs.utc(created_at),
                            'second') + 's'
                    }, 1000);
                } else {
                    let seconds = dayjs.utc(updated_at).diff(dayjs.utc(created_at), 'second')
                    this.finished_time = seconds + 's';
                }
            },
            measure_finished_time() {
                return this.finished_time;
            },
            measure_since_started() {
                return dayjs.utc(created_at).fromNow();
            }
        }))
    })
</script>
</div>
