<x-layout>
    <h1>Profile</h1>
    <div class="subtitle ">Your user profile settings.</div>
    <livewire:profile.form :request="$request" />
    <h2 class="py-4">Subscription</h2>
    <a href="{{ route('team.index') }}">Check in Team Settings</a>
    <h2 class="py-4">Two-factor Authentication</h2>
    @if (session('status') == 'two-factor-authentication-enabled')
        <div class="mb-4 font-medium">
            Please finish configuring two factor authentication below. Read the QR code or enter the secret key
            manually.
        </div>
        <div class="flex flex-col gap-2">
            <form action="/user/confirmed-two-factor-authentication" method="POST" class="flex items-end gap-2">
                @csrf
                <x-forms.input type="number" id="code" label="One-time code" required />
                <x-forms.button type="submit">Validate 2FA</x-forms.button>
            </form>
            <div>
                <div>{!! $request->user()->twoFactorQrCodeSvg() !!}</div>
                <div x-data="{ showCode: false }" class="py-2">
                    <template x-if="showCode">
                        <div class="py-2 ">{!! decrypt($request->user()->two_factor_secret) !!}</div>
                    </template>
                    <x-forms.button x-on:click="showCode = !showCode">Show secret key to manually
                        enter</x-forms.button>
                </div>
            </div>
        </div>
    @elseif(session('status') == 'two-factor-authentication-confirmed')
        <div class="mb-4 ">
            Two factor authentication confirmed and enabled successfully.
        </div>
        <div>
            <div class="pb-6 ">Here are the recovery codes for your account. Please store them in a secure
                location.
            </div>
            <div class="text-white">
                @foreach ($request->user()->recoveryCodes() as $code)
                    <div>{{ $code }}</div>
                @endforeach
            </div>
        </div>
    @else
        @if ($request->user()->two_factor_confirmed_at)
            <div class="pb-4 "> Two factor authentication is <span class="text-helper">enabled</span>.</div>
            <div class="flex gap-2">
                <form action="/user/two-factor-authentication" method="POST">
                    @csrf
                    @method ('DELETE')
                    <x-forms.button type="submit">Disable</x-forms.button>
                </form>
                <form action="/user/two-factor-recovery-codes" method="POST">
                    @csrf
                    <x-forms.button type="submit">Regenerate Recovery Codes</x-forms.button>
                </form>
            </div>
            @if (session('status') == 'recovery-codes-generated')
                <div>
                    <div class="py-6 ">Here are the recovery codes for your account. Please store them in a
                        secure
                        location.
                    </div>
                    <div class="text-white">
                        @foreach ($request->user()->recoveryCodes() as $code)
                            <div>{{ $code }}</div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <form action="/user/two-factor-authentication" method="POST">
                @csrf
                <x-forms.button type="submit">Configure 2FA</x-forms.button>
            </form>
        @endif
    @endif
    @if (session()->has('errors'))
        <div class="text-error">
            Something went wrong. Please try again.
        </div>
    @endif
</x-layout>
