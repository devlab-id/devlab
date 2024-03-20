<div>
    <div class="flex items-center gap-2">
        <h2>Transactional Email</h2>
    </div>
    <div class="pb-4 ">Email settings for password resets, invitations, shared with Pro+ subscribers etc.</div>
    <form wire:submit='submitFromFields' class="pb-4">
        <div class="flex flex-col items-end w-full gap-2 xl:flex-row">
            <x-forms.input required id="settings.smtp_from_name" helper="Name used in emails." label="From Name" />
            <x-forms.input required id="settings.smtp_from_address" helper="Email address used in emails."
                label="From Address" />
            <x-forms.button type="submit">
                Save
            </x-forms.button>
            @if (isEmailEnabled($settings) && auth()->user()->isAdminFromSession() && isTestEmailEnabled($settings))
                <x-modal-input buttonTitle="Send Test Email" title="Send Test Email">
                    <form wire:submit='submit' class="flex flex-col gap-2">
                        <x-forms.input placeholder="test@example.com" id="emails" label="Recipients" required />
                        <x-forms.button onclick="sendTestEmail.close()" wire:click="sendTestNotification"
                            @click="modalOpen=false">
                            Send Email
                        </x-forms.button>
                    </form>
                </x-modal-input>
            @endif
        </div>
    </form>
    <div class="flex flex-col gap-4">
        <div class="p-4 border border-coolgray-500">
            <h3>SMTP Server</h3>
            <div class="w-32">
                <x-forms.checkbox instantSave id="settings.smtp_enabled" label="Enabled" />
            </div>
            <form wire:submit='submit' class="flex flex-col">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col w-full gap-2 xl:flex-row">
                        <x-forms.input required id="settings.smtp_host" placeholder="smtp.mailgun.org" label="Host" />
                        <x-forms.input required id="settings.smtp_port" placeholder="587" label="Port" />
                        <x-forms.input id="settings.smtp_encryption" helper="If SMTP uses SSL, set it to 'tls'."
                            placeholder="tls" label="Encryption" />
                    </div>
                    <div class="flex flex-col w-full gap-2 xl:flex-row">
                        <x-forms.input id="settings.smtp_username" label="SMTP Username" />
                        <x-forms.input id="settings.smtp_password" type="password" label="SMTP Password" />
                        <x-forms.input id="settings.smtp_timeout" helper="Timeout value for sending emails."
                            label="Timeout" />
                    </div>
                </div>
                <div class="flex justify-end gap-4 pt-6">
                    <x-forms.button type="submit">
                        Save
                    </x-forms.button>
                </div>
            </form>
        </div>
        <div class="p-4 border border-coolgray-500">
            <h3>Resend</h3>
            <div class="w-32">
                <x-forms.checkbox instantSave='instantSaveResend' id="settings.resend_enabled" label="Enabled" />
            </div>
            <form wire:submit='submitResend' class="flex flex-col">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col w-full gap-2 xl:flex-row">
                        <x-forms.input type="password" id="settings.resend_api_key" placeholder="API key" required
                            label="Host" />
                    </div>
                </div>
                <div class="flex justify-end gap-4 pt-6">
                    <x-forms.button type="submit">
                        Save
                    </x-forms.button>
                </div>
            </form>
        </div>
    </div>
</div>
