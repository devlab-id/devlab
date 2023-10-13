<div class="flex flex-col-reverse gap-2">
    @forelse($executions as $execution)
        <form class="flex flex-col p-2 border-dotted border-1 bg-coolgray-300" @class([
            'border-green-500' => data_get($execution, 'status') === 'success',
            'border-red-500' => data_get($execution, 'status') === 'failed',
        ])>
            <div>Database: {{ data_get($execution, 'database_name', 'N/A') }}</div>
            <div>Status: {{ data_get($execution, 'status') }}</div>
            <div>Started At: {{ data_get($execution, 'created_at') }}</div>
            @if (data_get($execution, 'message'))
                <div>Message: {{ data_get($execution, 'message') }}</div>
            @endif
            <div>Size: {{ data_get($execution, 'size') }} B / {{ round((int) data_get($execution, 'size') / 1024, 2) }}
                kB / {{ round((int) data_get($execution, 'size') / 1024 / 1024, 3) }} MB
            </div>
            <div>Location: {{ data_get($execution, 'filename', 'N/A') }}</div>
            <livewire:project.database.backup-execution :execution="$execution" :wire:key="$execution->id" />
        </form>
    @empty
        <div>No executions found.</div>
    @endforelse
</div>
