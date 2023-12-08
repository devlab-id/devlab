<!DOCTYPE html>
<html data-theme="coollabs" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://api.fonts.coollabs.io" crossorigin>
    <link href="https://api.fonts.coollabs.io/css2?family=Inter&display=swap" rel="stylesheet">
    <meta name="robots" content="noindex">
    <title>Coolify</title>
    @env('local')
    <link rel="icon" href="{{ asset('favicon-dev.png') }}" type="image/x-icon" />
@else
    <link rel="icon" href="{{ asset('coolify-transparent.png') }}" type="image/x-icon" />
    @endenv
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/app.js', 'resources/css/app.css'])
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    @if (config('app.name') == 'Coolify Cloud')
        <script defer data-domain="app.coolify.io" src="https://analytics.coollabs.io/js/plausible.js"></script>
    @endif
    @auth
        <script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.15.3/echo.iife.min.js"
            integrity="sha512-aPAh2oRUr3ALz2MwVWkd6lmdgBQC0wSr0R++zclNjXZreT/JrwDPZQwA/p6R3wOCTcXKIHgA9pQGEQBWQmdLaA=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"
            integrity="sha512-tXL5mrkSoP49uQf2jO0LbvzMyFgki//znmq0wYXGq94gVF6TU0QlrSbwGuPpKTeN1mIjReeqKZ4/NJPjHN1d2Q=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    @endauth
</head>
@section('body')

    <body>
        @livewire('wire-elements-modal')
        <dialog id="help" class="modal">
            <livewire:help />
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
        <x-toaster-hub />
        <x-version class="fixed left-2 bottom-1" />

        <script data-navigate-once>
            let checkHealthInterval = null;
            let checkIfIamDeadInterval = null;

            function changePasswordFieldType(event) {
                let element = event.target
                for (let i = 0; i < 10; i++) {
                    if (element.className === "relative") {
                        break;
                    }
                    element = element.parentElement;
                }
                element = element.children[1];
                if (element.nodeName === 'INPUT') {
                    if (element.type === 'password') {
                        element.type = 'text';
                    } else {
                        element.type = 'password';
                    }
                }
            }

            function revive() {
                if (checkHealthInterval) return true;
                console.log('Checking server\'s health...')
                checkHealthInterval = setInterval(() => {
                    fetch('/api/health')
                        .then(response => {
                            if (response.ok) {
                                Toaster.success('Coolify is back online. Reloading...')
                                if (checkHealthInterval) clearInterval(checkHealthInterval);
                                setTimeout(() => {
                                    window.location.reload();
                                }, 5000)
                            } else {
                                console.log('Waiting for server to come back from dead...');
                            }
                        })
                }, 2000);
            }

            function upgrade() {
                if (checkIfIamDeadInterval) return true;
                console.log('Update initiated.')
                checkIfIamDeadInterval = setInterval(() => {
                    fetch('/api/health')
                        .then(response => {
                            if (response.ok) {
                                console.log('It\'s alive. Waiting for server to be dead...');
                            } else {
                                Toaster.success('Update done, restarting Coolify!')
                                console.log('It\'s dead. Reviving... Standby... Bzz... Bzz...')
                                if (checkIfIamDeadInterval) clearInterval(checkIfIamDeadInterval);
                                revive();
                            }
                        })
                }, 2000);
            }

            function copyToClipboard(text) {
                navigator?.clipboard?.writeText(text) && window.Livewire.emit('success', 'Copied to clipboard.');
            }
            document.addEventListener('livewire:init', () => {
                window.Livewire.on('reloadWindow', (timeout) => {
                    if (timeout) {
                        setTimeout(() => {
                            window.location.reload();
                        }, timeout);
                        return;
                    } else {
                        window.location.reload();
                    }
                })
                window.Livewire.on('info', (message) => {
                    if (message) Toaster.info(message)
                })
                window.Livewire.on('error', (message) => {
                    if (message) Toaster.error(message)
                })
                window.Livewire.on('warning', (message) => {
                    if (message) Toaster.warning(message)
                })
                window.Livewire.on('success', (message) => {
                    if (message) Toaster.success(message)
                })
                window.Livewire.on('installDocker', () => {
                    installDocker.showModal();
                })
            });
        </script>
    </body>
@show

</html>
