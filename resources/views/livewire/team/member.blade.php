<tr class="border-coolgray-200">
    {{-- <th class="text-warning">{{ $member->id }}</th> --}}
    <th>{{ $member->name }}</th>
    <td>{{ $member->email }}</td>
    <td>{{ data_get($member, 'pivot.role') }}</td>
    <td>
        {{-- TODO: This is not good --}}
        @if (auth()->user()->isAdmin())
            @if ($member->id !== auth()->user()->id)
                @if (data_get($member, 'pivot.role') !== 'owner')
                    @if (data_get($member, 'pivot.role') !== 'admin')
                        <x-forms.button wire:click="makeAdmin">Make admin</x-forms.button>
                    @else
                        <x-forms.button wire:click="makeReadonly">Make readonly</x-forms.button>
                    @endif
                    <x-forms.button wire:click="remove">Remove</x-forms.button>
                @else
                    <x-forms.button disabled>Remove</x-forms.button>
                @endif
            @else
                <x-forms.button disabled>Remove</x-forms.button>
            @endif
        @endif
    </td>
</tr>
