<div class="pb-5">
    <h1>Settings</h1>
    <div class="subtitle">Instance wide settings for Coolify.</div>
    <div class="navbar-main">
        <nav class="flex gap-4 overflow-x-scroll items-center min-h-10">
            <a class="{{ request()->routeIs('settings.index') ? 'dark:text-white' : '' }}"
                href="{{ route('settings.index') }}">
                <button>Configuration</button>
            </a>
            @if (isCloud())
                <a class="{{ request()->routeIs('settings.license') ? 'dark:text-white' : '' }}"
                    href="{{ route('settings.license') }}">
                    <button>Resale License</button>
                </a>
            @endif
            <div class="flex-1"></div>
        </nav>
    </div>
</div>
