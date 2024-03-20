<form class="flex flex-col gap-2 rounded" wire:submit='submit'>
    <x-forms.input placeholder="Your Cool Project" id="name" label="Name" required />
    <x-forms.input placeholder="This is my cool project everyone knows about" id="description" label="Description" />
    <div class="subtitle">New project will have a default production environment.</div>
    <x-forms.button type="submit" @click="slideOverOpen=false">
        Save
    </x-forms.button>
</form>
