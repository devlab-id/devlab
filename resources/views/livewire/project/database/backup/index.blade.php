<div>
    <h1>Backups</h1>
    <livewire:project.database.heading :database="$database" />
    <div class="pt-6">
        <div class="flex gap-2 ">
            <h2 class="pb-4">Scheduled Backups</h2>
            <x-modal-input buttonTitle="+ Add" title="New Scheduled Backup">
                <livewire:project.database.create-scheduled-backup :database="$database" :s3s="$s3s" />
            </x-modal-input>
        </div>
        <livewire:project.database.scheduled-backups :database="$database" />
    </div>
</div>
