<div class="flex flex-col gap-2">
    @if ($uptime)
        <div class="flex w-64 gap-2">Server is reachable: <svg class="w-5 h-5 text-success" viewBox="0 0 256 256"
                xmlns="http://www.w3.org/2000/svg">
                <g fill="currentColor">
                    <path
                        d="m237.66 85.26l-128.4 128.4a8 8 0 0 1-11.32 0l-71.6-72a8 8 0 0 1 0-11.31l24-24a8 8 0 0 1 11.32 0l36.68 35.32a8 8 0 0 0 11.32 0l92.68-91.32a8 8 0 0 1 11.32 0l24 23.6a8 8 0 0 1 0 11.31"
                        opacity=".2" />
                    <path
                        d="m243.28 68.24l-24-23.56a16 16 0 0 0-22.58 0L104 136l-.11-.11l-36.64-35.27a16 16 0 0 0-22.57.06l-24 24a16 16 0 0 0 0 22.61l71.62 72a16 16 0 0 0 22.63 0l128.4-128.38a16 16 0 0 0-.05-22.67M103.62 208L32 136l24-24l.11.11l36.64 35.27a16 16 0 0 0 22.52 0L208.06 56L232 79.6Z" />
                </g>
            </svg></div>
    @else
        <div class="w-64"><x-loading text="Server is reachable" /></div>
    @endif
    @isset($supported_os_type)
        <div class="flex w-64 gap-2">Supported OS type: <svg class="w-5 h-5 text-success" viewBox="0 0 256 256"
                xmlns="http://www.w3.org/2000/svg">
                <g fill="currentColor">
                    <path
                        d="m237.66 85.26l-128.4 128.4a8 8 0 0 1-11.32 0l-71.6-72a8 8 0 0 1 0-11.31l24-24a8 8 0 0 1 11.32 0l36.68 35.32a8 8 0 0 0 11.32 0l92.68-91.32a8 8 0 0 1 11.32 0l24 23.6a8 8 0 0 1 0 11.31"
                        opacity=".2" />
                    <path
                        d="m243.28 68.24l-24-23.56a16 16 0 0 0-22.58 0L104 136l-.11-.11l-36.64-35.27a16 16 0 0 0-22.57.06l-24 24a16 16 0 0 0 0 22.61l71.62 72a16 16 0 0 0 22.63 0l128.4-128.38a16 16 0 0 0-.05-22.67M103.62 208L32 136l24-24l.11.11l36.64 35.27a16 16 0 0 0 22.52 0L208.06 56L232 79.6Z" />
                </g>
            </svg></div>
    @endisset
    @if ($docker_installed)
        <div class="flex w-64 gap-2">Docker is installed: <svg class="w-5 h-5 text-success" viewBox="0 0 256 256"
                xmlns="http://www.w3.org/2000/svg">
                <g fill="currentColor">
                    <path
                        d="m237.66 85.26l-128.4 128.4a8 8 0 0 1-11.32 0l-71.6-72a8 8 0 0 1 0-11.31l24-24a8 8 0 0 1 11.32 0l36.68 35.32a8 8 0 0 0 11.32 0l92.68-91.32a8 8 0 0 1 11.32 0l24 23.6a8 8 0 0 1 0 11.31"
                        opacity=".2" />
                    <path
                        d="m243.28 68.24l-24-23.56a16 16 0 0 0-22.58 0L104 136l-.11-.11l-36.64-35.27a16 16 0 0 0-22.57.06l-24 24a16 16 0 0 0 0 22.61l71.62 72a16 16 0 0 0 22.63 0l128.4-128.38a16 16 0 0 0-.05-22.67M103.62 208L32 136l24-24l.11.11l36.64 35.27a16 16 0 0 0 22.52 0L208.06 56L232 79.6Z" />
                </g>
            </svg></div>
    @endif
    @isset($docker_version)
        <div class="flex w-64 gap-2">Minimum Docker version installed: <svg class="w-5 h-5 text-success"
                viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg">
                <g fill="currentColor">
                    <path
                        d="m237.66 85.26l-128.4 128.4a8 8 0 0 1-11.32 0l-71.6-72a8 8 0 0 1 0-11.31l24-24a8 8 0 0 1 11.32 0l36.68 35.32a8 8 0 0 0 11.32 0l92.68-91.32a8 8 0 0 1 11.32 0l24 23.6a8 8 0 0 1 0 11.31"
                        opacity=".2" />
                    <path
                        d="m243.28 68.24l-24-23.56a16 16 0 0 0-22.58 0L104 136l-.11-.11l-36.64-35.27a16 16 0 0 0-22.57.06l-24 24a16 16 0 0 0 0 22.61l71.62 72a16 16 0 0 0 22.63 0l128.4-128.38a16 16 0 0 0-.05-22.67M103.62 208L32 136l24-24l.11.11l36.64 35.27a16 16 0 0 0 22.52 0L208.06 56L232 79.6Z" />
                </g>
            </svg></div>
    @endisset
    <livewire:new-activity-monitor header="Docker Installation" />
    @isset($error)
        <pre class="font-bold whitespace-pre-line text-error">{!! $error !!}</pre>
    @endisset
</div>
