<div>
    <div class="flex items-end gap-2">
        <h1>Create a new Application</h1>
        <x-forms.button wire:click="saveFromRedirect('source.new')" class="group-hover:text-white">
            + Add New GitHub App
        </x-forms.button>
    </div>
    <div class="pb-4">Deploy any public or private git repositories through a GitHub App.</div>
    @if ($github_apps->count() !== 0)
        <div class="flex flex-col gap-2 pt-10">
            @if ($current_step === 'github_apps')
                <ul class="pb-10 steps">
                    <li class="step step-secondary">Select a GitHub App</li>
                    <li class="step">Select a Repository, Branch & Save</li>
                </ul>
                <div class="flex flex-col justify-center gap-2 text-left xl:flex-row">
                    @foreach ($github_apps as $ghapp)
                        <div class="gap-2 py-4 cursor-pointer group hover:bg-coollabs bg-coolgray-200"
                            wire:click.prevent="loadRepositories({{ $ghapp->id }})" wire:key="{{ $ghapp->id }}">
                            <div class="flex mr-4">
                                <div class="flex flex-col mx-6">
                                    <div class="group-hover:text-white">
                                        {{ data_get($ghapp, 'name') }}
                                    </div>
                                    <div class="text-xs text-gray-400 group-hover:text-white">
                                        {{ data_get($ghapp, 'html_url') }}</div>

                                </div>
                                <span wire:target="loadRepositories({{ $ghapp->id }})" wire:loading.delay
                                    class="loading loading-xs text-warning loading-spinner"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($current_step === 'repository')
                <ul class="pb-10 steps">
                    <li class="step step-secondary">Select a GitHub App</li>
                    <li class="step step-secondary">Select a Repository, Branch & Save</li>
                </ul>
                @if ($repositories->count() > 0)
                    <div class="flex items-end gap-2">
                        <x-forms.select class="w-full" label="Repository URL" helper="{!! __('repository.url') !!}"
                            wire:model.defer="selected_repository_id">
                            @foreach ($repositories as $repo)
                                @if ($loop->first)
                                    <option selected value="{{ data_get($repo, 'id') }}">
                                        {{ data_get($repo, 'name') }}
                                    </option>
                                @else
                                    <option value="{{ data_get($repo, 'id') }}">{{ data_get($repo, 'name') }}
                                    </option>
                                @endif
                            @endforeach
                        </x-forms.select>
                        <x-forms.button wire:click.prevent="loadBranches"> Load Repository Details </x-forms.button>
                        <a target="_blank" class="flex hover:no-underline"
                            href="{{ get_installation_path($github_app) }}">
                            <x-forms.button>
                                Change Repositories on GitHub
                                <x-external-link />
                            </x-forms.button>
                        </a>
                    </div>
                @else
                    <div>No repositories found. Check your GitHub App configuration.</div>
                @endif
                @if ($branches->count() > 0)
                    <div class="flex flex-col gap-2 pb-6">
                        <form class="flex flex-col" wire:submit.prevent='submit'>
                            <div class="flex flex-col gap-2 pb-6">
                                <div class="flex gap-2">
                                    <x-forms.select id="selected_branch_name" label="Branch">
                                        <option value="default" disabled selected>Select a branch</option>
                                        @foreach ($branches as $branch)
                                            @if ($loop->first)
                                                <option selected value="{{ data_get($branch, 'name') }}">
                                                    {{ data_get($branch, 'name') }}
                                                </option>
                                            @else
                                                <option value="{{ data_get($branch, 'name') }}">
                                                    {{ data_get($branch, 'name') }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </x-forms.select>
                                    @if ($is_static)
                                        <x-forms.input id="publish_directory" label="Publish Directory"
                                            helper="If there is a build process involved (like Svelte, React, Next, etc..), please specify the output directory for the build assets." />
                                    @else
                                        <x-forms.input type="number" id="port" label="Port" :readonly="$is_static"
                                            helper="The port your application listens on." />
                                    @endif
                                </div>
                                <div class="w-52">
                                    <x-forms.checkbox instantSave id="is_static" label="Is it a static site?"
                                        helper="If your application is a static site or the final build assets should be served as a static site, enable this." />
                                </div>
                            </div>
                            <x-forms.button type="submit">
                                Save New Application
                            </x-forms.button>
                @endif
            @endif
        </div>
    @else
        <div class="hero">
            No GitHub Application found. Please create a new GitHub Application.
        </div>
    @endif
</div>
