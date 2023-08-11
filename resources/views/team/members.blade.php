<x-layout>
    <x-team.navbar :team="auth()
        ->user()
        ->currentTeam()" />
    <h2>Members</h2>
    <div class="pt-4 overflow-hidden">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach (auth()->user()->currentTeam()->members->sortBy('name') as $member)
                    <livewire:team.member :member="$member" :wire:key="$member->id" />
                @endforeach
            </tbody>
        </table>
    </div>
    @if (auth()->user()->isAdminFromSession())
        <div class="py-4">
            @if (is_transactional_emails_active())
                <h3 class="pb-4">Invite a new member</h3>
            @else
                <h3>Invite a new member</h3>
                @if (auth()->user()->isInstanceAdmin())
                    <div class="pb-4 text-xs text-warning">You need to configure <a href="/settings/emails"
                            class="underline text-warning">Transactional
                            Emails</a>
                        before
                        you can invite a
                        new
                        member
                        via
                        email.
                    </div>
                @endif
            @endif
            <livewire:team.invite-link />
        </div>
        <livewire:team.invitations :invitations="$invitations" />
    @endif
</x-layout>
