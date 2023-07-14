<?php

namespace App\Jobs;

use App\Enums\ApplicationDeploymentStatus;
use App\Enums\ProxyTypes;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationPreview;
use App\Models\GithubApp;
use App\Models\GitlabApp;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\SwarmDocker;
use App\Traits\ExecuteRemoteCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Url\Url;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Visus\Cuid2\Cuid2;

class ApplicationDeploymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ExecuteRemoteCommand;

    public static int $batch_counter = 0;

    private int $application_deployment_queue_id;

    private ApplicationDeploymentQueue $application_deployment_queue;
    private Application $application;
    private string $deployment_uuid;
    private int $pull_request_id;
    private string $commit;
    private bool $force_rebuild;

    private GithubApp|GitlabApp $source;
    private StandaloneDocker|SwarmDocker $destination;
    private Server $server;
    private string $private_key_location;
    private ApplicationPreview|null $preview = null;

    private string $container_name;
    private string $workdir;
    private string $build_workdir;
    private string $build_image_name;
    private string $production_image_name;
    private bool $is_debug_enabled;
    private $build_args;
    private $env_args;
    private $docker_compose;

    private $log_model;
    private Collection $saved_outputs;
    public function __construct(int $application_deployment_queue_id)
    {
        ray()->clearScreen();
        $this->application_deployment_queue = ApplicationDeploymentQueue::find($application_deployment_queue_id);
        $this->log_model = $this->application_deployment_queue;
        $this->application = Application::find($this->application_deployment_queue->application_id);

        $this->application_deployment_queue_id = $application_deployment_queue_id;
        $this->deployment_uuid = $this->application_deployment_queue->deployment_uuid;
        $this->pull_request_id = $this->application_deployment_queue->pull_request_id;
        $this->commit = $this->application_deployment_queue->commit;
        $this->force_rebuild = $this->application_deployment_queue->force_rebuild;

        $this->source = $this->application->source->getMorphClass()::where('id', $this->application->source->id)->first();
        $this->destination = $this->application->destination->getMorphClass()::where('id', $this->application->destination->id)->first();
        $this->server = $this->destination->server;
        $this->private_key_location = save_private_key_for_server($this->server);

        $this->workdir = "/artifacts/{$this->deployment_uuid}";
        $this->build_workdir = "{$this->workdir}" . rtrim($this->application->base_directory, '/');
        $this->is_debug_enabled = $this->application->settings->is_debug_enabled;

        $this->container_name = generate_container_name($this->application->uuid, $this->pull_request_id);
        $this->private_key_location = save_private_key_for_server($this->server);
        $this->saved_outputs = collect();

        // Set preview fqdn
        if ($this->pull_request_id !== 0) {
            $this->preview = ApplicationPreview::findPreviewByApplicationAndPullId($this->application->id, $this->pull_request_id);
            if ($this->application->fqdn) {
                $preview_fqdn = data_get($this->preview, 'fqdn');
                $template = $this->application->preview_url_template;
                $url = Url::fromString($this->application->fqdn);
                $host = $url->getHost();
                $schema = $url->getScheme();
                $random = new Cuid2(7);
                $preview_fqdn = str_replace('{{random}}', $random, $template);
                $preview_fqdn = str_replace('{{domain}}', $host, $preview_fqdn);
                $preview_fqdn = str_replace('{{pr_id}}', $this->pull_request_id, $preview_fqdn);
                $preview_fqdn = "$schema://$preview_fqdn";
                $this->preview->fqdn = $preview_fqdn;
                $this->preview->save();
            }
        }
    }

    public function handle(): void
    {
        // ray()->measure();
        $this->application_deployment_queue->update([
            'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
        ]);
        try {
            if ($this->pull_request_id !== 0) {
                $this->deploy_pull_request();
            } else {
                $this->deploy();
            }
            if ($this->application->fqdn) dispatch(new ProxyStartJob($this->server));
            $this->next(ApplicationDeploymentStatus::FINISHED->value);
        } catch (\Exception $e) {
            ray($e);
            $this->fail($e);
        } finally {
            if (isset($this->docker_compose)) {
                Storage::disk('deployments')->put(Str::kebab($this->application->name) . '/docker-compose.yml', $this->docker_compose);
            }
            $this->execute_remote_command(
                [
                    "docker rm -f {$this->deployment_uuid} >/dev/null 2>&1",
                    "hidden" => true,
                ]
            );
            // ray()->measure();
        }
    }
    public function failed(Throwable $exception): void
    {
        $this->execute_remote_command(
            ["echo 'Oops something is not okay, are you okay? 😢'"],
            ["echo '{$exception->getMessage()}'"]
        );
        $this->next(ApplicationDeploymentStatus::FAILED->value);
    }
    private function execute_in_builder(string $command)
    {
        return "docker exec {$this->deployment_uuid} bash -c '{$command}'";
        // return "docker exec {$this->deployment_uuid} bash -c '{$command} |& tee -a /proc/1/fd/1; [ \$PIPESTATUS -eq 0 ] || exit \$PIPESTATUS'";
    }
    private function deploy()
    {

        $this->execute_remote_command(
            [
                "echo 'Starting deployment of {$this->application->git_repository}:{$this->application->git_branch}.'"
            ],
        );
        $this->prepare_builder_image();
        $this->clone_repository();

        $tag = Str::of("{$this->commit}-{$this->application->id}-{$this->pull_request_id}");
        if (strlen($tag) > 128) {
            $tag = $tag->substr(0, 128);
        };

        $this->build_image_name = "{$this->application->git_repository}:{$tag}-build";
        $this->production_image_name = "{$this->application->uuid}:{$tag}";
        ray('Build Image Name: ' . $this->build_image_name . ' & Production Image Name: ' . $this->production_image_name)->green();

        if (!$this->force_rebuild) {
            $this->execute_remote_command([
                "docker images -q {$this->production_image_name} 2>/dev/null", "hidden" => true, "save" => "local_image_found"
            ]);
            if (Str::of($this->saved_outputs->get('local_image_found'))->isNotEmpty()) {
                $this->execute_remote_command([
                    "echo 'Docker Image found locally with the same Git Commit SHA {$this->application->uuid}:{$this->commit}. Build step skipped...'"
                ]);
                $this->generate_compose_file();
                $this->stop_running_container();
                $this->start_by_compose_file();
                return;
            }
        }
        $this->cleanup_git();
        $this->generate_buildpack();
        $this->generate_compose_file();
        $this->generate_build_env_variables();
        $this->add_build_env_variables_to_dockerfile();
        $this->build_image();
        $this->stop_running_container();
        $this->start_by_compose_file();
    }
    private function deploy_pull_request()
    {
        $this->build_image_name = "{$this->application->uuid}:pr-{$this->pull_request_id}-build";
        $this->production_image_name = "{$this->application->uuid}:pr-{$this->pull_request_id}";
        ray('Build Image Name: ' . $this->build_image_name . ' & Production Image Name: ' . $this->production_image_name)->green();
        $this->execute_remote_command([
            "echo 'Starting pull request (#{$this->pull_request_id}) deployment of {$this->application->git_repository}:{$this->application->git_branch}.'",
        ]);
        $this->prepare_builder_image();
        $this->clone_repository();
        $this->cleanup_git();
        $this->generate_buildpack();
        $this->generate_compose_file();
        // Needs separate preview variables
        // $this->generate_build_env_variables();
        // $this->add_build_env_variables_to_dockerfile();
        $this->build_image();
        $this->stop_running_container();
        $this->start_by_compose_file();
    }

    private function next(string $status)
    {
        // If the deployment is cancelled by the user, don't update the status
        if ($this->application_deployment_queue->status !== ApplicationDeploymentStatus::CANCELLED_BY_USER->value) {
            $this->application_deployment_queue->update([
                'status' => $status,
            ]);
        }
        queue_next_deployment($this->application);
    }
    private function start_by_compose_file()
    {
        $this->execute_remote_command(
            ["echo -n 'Starting new application... '"],
            [$this->execute_in_builder("docker compose --project-directory {$this->workdir} up -d >/dev/null"), "hidden" => true],
            ["echo 'Done. 🎉'"],
        );
    }
    private function stop_running_container()
    {
        $this->execute_remote_command(
            ["echo -n 'Removing old running application.'"],
            [$this->execute_in_builder("docker rm -f $this->container_name >/dev/null 2>&1"), "hidden" => true],
        );
    }
    private function build_image()
    {
        $this->execute_remote_command([
            "echo -n 'Building docker image.'",
        ]);

        if ($this->application->settings->is_static) {
            $this->execute_remote_command([
                $this->execute_in_builder("docker build -f {$this->workdir}/Dockerfile {$this->build_args} --progress plain -t $this->build_image_name {$this->workdir}"), "hidden" => true
            ]);

            $dockerfile = base64_encode("FROM {$this->application->static_image}
WORKDIR /usr/share/nginx/html/
LABEL coolify.deploymentId={$this->deployment_uuid}
COPY --from=$this->build_image_name /app/{$this->application->publish_directory} .
COPY ./nginx.conf /etc/nginx/conf.d/default.conf");

            $nginx_config = base64_encode("server {
                listen       80;
                listen  [::]:80;
                server_name  localhost;
            
                location / {
                    root   /usr/share/nginx/html;
                    index  index.html;
                    try_files \$uri \$uri.html \$uri/index.html \$uri/ /index.html =404;
                }
            
                error_page   500 502 503 504  /50x.html;
                location = /50x.html {
                    root   /usr/share/nginx/html;
                }
            }");
            $this->execute_remote_command(
                [
                    $this->execute_in_builder("echo '{$dockerfile}' | base64 -d > {$this->workdir}/Dockerfile-prod")
                ],
                [
                    $this->execute_in_builder("echo '{$nginx_config}' | base64 -d > {$this->workdir}/nginx.conf")
                ],
                [
                    $this->execute_in_builder("docker build -f {$this->workdir}/Dockerfile-prod {$this->build_args} --progress plain -t $this->production_image_name {$this->workdir}"), "hidden" => true
                ]
            );
        } else {
            $this->execute_remote_command([
                $this->execute_in_builder("docker build -f {$this->workdir}/Dockerfile {$this->build_args} --progress plain -t $this->production_image_name {$this->workdir}"), "hidden" => true
            ]);
        }
    }
    private function add_build_env_variables_to_dockerfile()
    {
        $this->execute_remote_command([
            $this->execute_in_builder("cat {$this->workdir}/Dockerfile"), "hidden" => true, "save" => 'dockerfile'
        ]);
        $dockerfile = collect(Str::of($this->saved_outputs->get('dockerfile'))->trim()->explode("\n"));

        foreach ($this->application->build_environment_variables as $env) {
            $dockerfile->splice(1, 0, "ARG {$env->key}={$env->value}");
        }
        $dockerfile_base64 = base64_encode($dockerfile->implode("\n"));
        $this->execute_remote_command([
            $this->execute_in_builder("echo '{$dockerfile_base64}' | base64 -d > {$this->workdir}/Dockerfile"),
            "hidden" => true
        ]);
    }
    private function generate_build_env_variables()
    {
        $this->build_args = collect(["--build-arg SOURCE_COMMIT={$this->commit}"]);
        if ($this->pull_request_id === 0) {
            foreach ($this->application->build_environment_variables as $env) {
                $this->build_args->push("--build-arg {$env->key}={$env->value}");
            }
        } else {
            foreach ($this->application->build_environment_variables_preview as $env) {
                $this->build_args->push("--build-arg {$env->key}={$env->value}");
            }
        }

        $this->build_args = $this->build_args->implode(' ');
    }

    private function generate_compose_file()
    {
        $ports = $this->application->settings->is_static ? [80] : $this->application->ports_exposes_array;

        $persistent_storages = $this->generate_local_persistent_volumes();
        $volume_names = $this->generate_local_persistent_volumes_only_volume_names();
        $environment_variables = $this->generate_environment_variables($ports);

        $docker_compose = [
            'version' => '3.8',
            'services' => [
                $this->container_name => [
                    'image' => $this->production_image_name,
                    'container_name' => $this->container_name,
                    'restart' => 'always',
                    'environment' => $environment_variables,
                    'labels' => $this->set_labels_for_applications(),
                    'expose' => $ports,
                    'networks' => [
                        $this->destination->network,
                    ],
                    'healthcheck' => [
                        'test' => [
                            'CMD-SHELL',
                            $this->generate_healthcheck_commands()
                        ],
                        'interval' => $this->application->health_check_interval . 's',
                        'timeout' => $this->application->health_check_timeout . 's',
                        'retries' => $this->application->health_check_retries,
                        'start_period' => $this->application->health_check_start_period . 's'
                    ],
                    'mem_limit' => $this->application->limits_memory,
                    'memswap_limit' => $this->application->limits_memory_swap,
                    'mem_swappiness' => $this->application->limits_memory_swappiness,
                    'mem_reservation' => $this->application->limits_memory_reservation,
                    'cpus' => $this->application->limits_cpus,
                    'cpuset' => $this->application->limits_cpuset,
                    'cpu_shares' => $this->application->limits_cpu_shares,
                ]
            ],
            'networks' => [
                $this->destination->network => [
                    'external' => false,
                    'name' => $this->destination->network,
                    'attachable' => true,
                ]
            ]
        ];
        if (count($this->application->ports_mappings_array) > 0 && $this->pull_request_id === 0) {
            $docker_compose['services'][$this->container_name]['ports'] = $this->application->ports_mappings_array;
        }
        if (count($persistent_storages) > 0) {
            $docker_compose['services'][$this->container_name]['volumes'] = $persistent_storages;
        }
        if (count($volume_names) > 0) {
            $docker_compose['volumes'] = $volume_names;
        }
        $this->docker_compose = Yaml::dump($docker_compose, 10);
        $docker_compose_base64 = base64_encode($this->docker_compose);
        $this->execute_remote_command([$this->execute_in_builder("echo '{$docker_compose_base64}' | base64 -d > {$this->workdir}/docker-compose.yml"), "hidden" => true]);
    }
    private function generate_local_persistent_volumes()
    {
        $local_persistent_volumes = [];
        foreach ($this->application->persistentStorages as $persistentStorage) {
            $volume_name = $persistentStorage->host_path ?? $persistentStorage->name;
            if ($this->pull_request_id !== 0) {
                $volume_name = $volume_name . '-pr-' . $this->pull_request_id;
            }
            $local_persistent_volumes[] = $volume_name . ':' . $persistentStorage->mount_path;
        }
        return $local_persistent_volumes;
    }
    private function generate_local_persistent_volumes_only_volume_names()
    {
        $local_persistent_volumes_names = [];
        foreach ($this->application->persistentStorages as $persistentStorage) {
            if ($persistentStorage->host_path) {
                continue;
            }
            $name = $persistentStorage->name;

            if ($this->pull_request_id !== 0) {
                $name = $name . '-pr-' . $this->pull_request_id;
            }

            $local_persistent_volumes_names[$name] = [
                'name' => $name,
                'external' => false,
            ];
        }
        return $local_persistent_volumes_names;
    }
    private function generate_environment_variables($ports)
    {
        $environment_variables = collect();
        ray('Generate Environment Variables')->green();
        if ($this->pull_request_id === 0) {
            ray($this->application->runtime_environment_variables)->green();
            foreach ($this->application->runtime_environment_variables as $env) {
                $environment_variables->push("$env->key=$env->value");
            }
        } else {
            ray($this->application->runtime_environment_variables_preview)->green();
            foreach ($this->application->runtime_environment_variables_preview as $env) {
                $environment_variables->push("$env->key=$env->value");
            }
        }
        // Add PORT if not exists, use the first port as default
        if ($environment_variables->filter(fn ($env) => Str::of($env)->contains('PORT'))->isEmpty()) {
            $environment_variables->push("PORT={$ports[0]}");
        }
        return $environment_variables->all();
    }
    private function generate_healthcheck_commands()
    {
        if (!$this->application->health_check_port) {
            $this->application->health_check_port = $this->application->ports_exposes_array[0];
        }
        if ($this->application->health_check_path) {
            $generated_healthchecks_commands = [
                "curl -s -X {$this->application->health_check_method} -f {$this->application->health_check_scheme}://{$this->application->health_check_host}:{$this->application->health_check_port}{$this->application->health_check_path} > /dev/null"
            ];
        } else {
            $generated_healthchecks_commands = [
                "curl -s -X {$this->application->health_check_method} -f {$this->application->health_check_scheme}://{$this->application->health_check_host}:{$this->application->health_check_port}/"
            ];
        }
        return implode(' ', $generated_healthchecks_commands);
    }
    private function set_labels_for_applications()
    {
        $labels = [];
        $labels[] = 'coolify.managed=true';
        $labels[] = 'coolify.version=' . config('version');
        $labels[] = 'coolify.applicationId=' . $this->application->id;
        $labels[] = 'coolify.type=application';
        $labels[] = 'coolify.name=' . $this->application->name;
        if ($this->pull_request_id !== 0) {
            $labels[] = 'coolify.pullRequestId=' . $this->pull_request_id;
        }
        if ($this->application->fqdn) {
            if ($this->pull_request_id !== 0) {
                $domains = Str::of(data_get($this->preview, 'fqdn'))->explode(',');
            } else {
                $domains = Str::of(data_get($this->application, 'fqdn'))->explode(',');
            }
            if ($this->application->destination->server->proxy->type === ProxyTypes::TRAEFIK_V2->value) {
                $labels[] = 'traefik.enable=true';
                foreach ($domains as $domain) {
                    $url = Url::fromString($domain);
                    $host = $url->getHost();
                    $path = $url->getPath();
                    $schema = $url->getScheme();
                    $slug = Str::slug($host . $path);

                    $http_label = "{$this->application->uuid}-{$slug}-http";
                    $https_label = "{$this->application->uuid}-{$slug}-https";

                    if ($schema === 'https') {
                        // Set labels for https
                        $labels[] = "traefik.http.routers.{$https_label}.rule=Host(`{$host}`) && PathPrefix(`{$path}`)";
                        $labels[] = "traefik.http.routers.{$https_label}.entryPoints=https";
                        $labels[] = "traefik.http.routers.{$https_label}.middlewares=gzip";
                        if ($path !== '/') {
                            $labels[] = "traefik.http.routers.{$https_label}.middlewares={$https_label}-stripprefix";
                            $labels[] = "traefik.http.middlewares.{$https_label}-stripprefix.stripprefix.prefixes={$path}";
                        }

                        $labels[] = "traefik.http.routers.{$https_label}.tls=true";
                        $labels[] = "traefik.http.routers.{$https_label}.tls.certresolver=letsencrypt";

                        // Set labels for http (redirect to https)
                        $labels[] = "traefik.http.routers.{$http_label}.rule=Host(`{$host}`) && PathPrefix(`{$path}`)";
                        $labels[] = "traefik.http.routers.{$http_label}.entryPoints=http";
                        if ($this->application->settings->is_force_https_enabled) {
                            $labels[] = "traefik.http.routers.{$http_label}.middlewares=redirect-to-https";
                        }
                    } else {
                        // Set labels for http
                        $labels[] = "traefik.http.routers.{$http_label}.rule=Host(`{$host}`) && PathPrefix(`{$path}`)";
                        $labels[] = "traefik.http.routers.{$http_label}.entryPoints=http";
                        $labels[] = "traefik.http.routers.{$http_label}.middlewares=gzip";
                        if ($path !== '/') {
                            $labels[] = "traefik.http.routers.{$http_label}.middlewares={$http_label}-stripprefix";
                            $labels[] = "traefik.http.middlewares.{$http_label}-stripprefix.stripprefix.prefixes={$path}";
                        }
                    }
                }
            }
        }
        return $labels;
    }
    private function generate_buildpack()
    {
        $this->execute_remote_command(
            [
                "echo -n 'Generating nixpacks configuration.'",
            ],
            [$this->nixpacks_build_cmd()],
            [$this->execute_in_builder("cp {$this->workdir}/.nixpacks/Dockerfile {$this->workdir}/Dockerfile")],
            [$this->execute_in_builder("rm -f {$this->workdir}/.nixpacks/Dockerfile")]
        );
    }
    private function nixpacks_build_cmd()
    {
        $this->generate_env_variables();
        $nixpacks_command = "nixpacks build -o {$this->workdir} {$this->env_args} --no-error-without-start";
        if ($this->application->build_command) {
            $nixpacks_command .= " --build-cmd \"{$this->application->build_command}\"";
        }
        if ($this->application->start_command) {
            $nixpacks_command .= " --start-cmd \"{$this->application->start_command}\"";
        }
        if ($this->application->install_command) {
            $nixpacks_command .= " --install-cmd \"{$this->application->install_command}\"";
        }
        $nixpacks_command .= " {$this->workdir}";
        return $this->execute_in_builder($nixpacks_command);
    }
    private function generate_env_variables()
    {
        $this->env_args = collect([]);
        if ($this->pull_request_id === 0) {
            foreach ($this->application->nixpacks_environment_variables as $env) {
                $this->env_args->push("--env {$env->key}={$env->value}");
            }
        } else {
            foreach ($this->application->nixpacks_environment_variables_preview as $env) {
                $this->env_args->push("--env {$env->key}={$env->value}");
            }
        }

        $this->env_args = $this->env_args->implode(' ');
    }
    private function cleanup_git()
    {
        $this->execute_remote_command(
            [$this->execute_in_builder("rm -fr {$this->workdir}/.git")],
        );
    }
    private function prepare_builder_image()
    {
        $this->execute_remote_command(
            [
                "echo -n 'Pulling latest version of the builder image (ghcr.io/coollabsio/coolify-builder).'",
            ],
            [
                "docker run --pull=always -d --name {$this->deployment_uuid} --rm -v /var/run/docker.sock:/var/run/docker.sock ghcr.io/coollabsio/coolify-builder",
                "hidden" => true,
            ],
            [
                "command" => $this->execute_in_builder("mkdir -p {$this->workdir}")
            ],
        );
    }
    private function set_git_import_settings($git_clone_command)
    {
        if ($this->application->git_commit_sha !== 'HEAD') {
            $git_clone_command = "{$git_clone_command} && cd {$this->workdir} && git -c advice.detachedHead=false checkout {$this->application->git_commit_sha} >/dev/null 2>&1";
        }
        if ($this->application->settings->is_git_submodules_enabled) {
            $git_clone_command = "{$git_clone_command} && cd {$this->workdir} && git submodule update --init --recursive";
        }
        if ($this->application->settings->is_git_lfs_enabled) {
            $git_clone_command = "{$git_clone_command} && cd {$this->workdir} && git lfs pull";
        }
        return $git_clone_command;
    }
    private function importing_git_repository()
    {
        $commands = collect([]);
        $git_clone_command = "git clone -q -b {$this->application->git_branch}";
        if ($this->pull_request_id !== 0) {
            $pr_branch_name = "pr-{$this->pull_request_id}-coolify";
        }

        if ($this->application->deploymentType() === 'source') {
            $source_html_url = data_get($this->application, 'source.html_url');
            $url = parse_url(filter_var($source_html_url, FILTER_SANITIZE_URL));
            $source_html_url_host = $url['host'];
            $source_html_url_scheme = $url['scheme'];

            if ($this->source->getMorphClass() == 'App\Models\GithubApp') {
                if ($this->source->is_public) {
                    $git_clone_command = "{$git_clone_command} {$this->source->html_url}/{$this->application->git_repository} {$this->workdir}";
                    $git_clone_command = $this->set_git_import_settings($git_clone_command);

                    $commands->push($this->execute_in_builder($git_clone_command));
                } else {
                    $github_access_token = generate_github_installation_token($this->source);
                    $commands->push($this->execute_in_builder("git clone -q -b {$this->application->git_branch} $source_html_url_scheme://x-access-token:$github_access_token@$source_html_url_host/{$this->application->git_repository}.git {$this->workdir}"));
                }
                if ($this->pull_request_id !== 0) {
                    $commands->push($this->execute_in_builder("cd {$this->workdir} && git fetch origin pull/{$this->pull_request_id}/head:$pr_branch_name && git checkout $pr_branch_name"));
                }
                return $commands->implode(' && ');
            }
        }
        if ($this->application->deploymentType() === 'deploy_key') {
            $private_key = base64_encode($this->application->private_key->private_key);
            $git_clone_command = "GIT_SSH_COMMAND=\"ssh -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i /root/.ssh/id_rsa\" {$git_clone_command} {$this->application->git_full_url} {$this->workdir}";
            $git_clone_command = $this->set_git_import_settings($git_clone_command);
            $commands = collect([
                $this->execute_in_builder("mkdir -p /root/.ssh"),
                $this->execute_in_builder("echo '{$private_key}' | base64 -d > /root/.ssh/id_rsa"),
                $this->execute_in_builder("chmod 600 /root/.ssh/id_rsa"),
                $this->execute_in_builder($git_clone_command)
            ]);
            return $commands->implode(' && ');
        }
    }
    private function clone_repository()
    {
        $this->execute_remote_command(
            [
                "echo -n 'Importing {$this->application->git_repository}:{$this->application->git_branch} to {$this->workdir}. '"
            ],
            [
                $this->importing_git_repository()
            ],
            [
                $this->execute_in_builder("cd {$this->workdir} && git rev-parse HEAD"),
                "hidden" => true,
                "save" => "git_commit_sha"
            ],
        );
        $this->commit = $this->saved_outputs->get('git_commit_sha');
    }
}
