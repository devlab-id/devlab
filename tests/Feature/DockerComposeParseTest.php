<?php

use App\Models\Application;
use App\Models\GithubApp;
use App\Models\Server;
use App\Models\StandaloneDocker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

ray()->clearAll();
beforeEach(function () {
    $this->composeFile = [
        'version' => '3.8',
        'services' => [
            'app' => [
                'image' => 'nginx',
                'environment' => [
                    'SERVICE_FQDN_APP' => '/app',
                    'APP_KEY' => 'base64',
                    'APP_DEBUG' => '${APP_DEBUG:-false}',
                    'APP_URL' => '$SERVICE_FQDN_APP',
                ],
                'volumes' => [
                    './nginx:/etc/nginx',
                    'data:/var/www/html',
                ],
                'depends_on' => [
                    'db',
                ],
            ],
            'db' => [
                'image' => 'postgres',
                'environment' => [
                    'POSTGRES_USER' => '${POSTGRES_USER:-postgres}',
                    'POSTGRES_PASSWORD' => '${POSTGRES_PASSWORD:-postgres}',
                ],
                'volumes' => [
                    'dbdata:/var/lib/postgresql/data',
                ],
                'healthcheck' => [
                    'test' => ['CMD', 'pg_isready', '-U', 'postgres'],
                    'interval' => '2s',
                    'timeout' => '10s',
                    'retries' => 10,
                ],
                'depends_on' => [
                    'app' => [
                        'condition' => 'service_healthy',
                    ],
                ],

            ],

        ],
        'networks' => [
            'default' => [
                'name' => 'something',
                'external' => true,
            ],
            'noinet' => [
                'driver' => 'bridge',
                'internal' => true,
            ],
        ],
    ];
    $this->composeFileString = Yaml::dump($this->composeFile, 10, 2);
    $this->jsonComposeFile = json_encode($this->composeFile, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

    $this->application = Application::create([
        'name' => 'Application for tests',
        'uuid' => 'bcoowoookw0co4cok4sgc4k8',
        'repository_project_id' => 603035348,
        'git_repository' => 'coollabsio/coolify-examples',
        'git_branch' => 'main',
        'base_directory' => '/docker-compose-test',
        'docker_compose_location' => 'docker-compose.yml',
        'docker_compose_raw' => $this->composeFileString,
        'build_pack' => 'dockercompose',
        'ports_exposes' => '3000',
        'environment_id' => 1,
        'destination_id' => 0,
        'destination_type' => StandaloneDocker::class,
        'source_id' => 1,
        'source_type' => GithubApp::class,
    ]);
});

afterEach(function () {
    $this->application->forceDelete();
});

test('ComposeParse', function () {
    expect($this->jsonComposeFile)->toBeJson()->ray();

    $output = $this->application->dockerComposeParser();
    $outputOld = $this->application->parseCompose();
    expect($output)->toBeInstanceOf(Collection::class);
    expect($outputOld)->toBeInstanceOf(Collection::class);

    ray(Yaml::dump($output->toArray(), 10, 2));
    $services = $output->get('services');
    $servicesCount = count($this->composeFile['services']);
    expect($services)->toHaveCount($servicesCount);

    $app = $services->get("app");
    expect($app)->not->toBeNull();

    $db = $services->get("db");
    expect($db)->not->toBeNull();

    $appDependsOn = $app->get('depends_on');
    expect($appDependsOn)->toContain('db');

    $dbDependsOn = $db->get('depends_on');

    expect($dbDependsOn->keys()->first())->toContain('app');
    expect(data_get($dbDependsOn, 'app.condition'))->toBe('service_healthy');


    $environment = $app->get('environment');
    expect($environment)->not->toBeNull();

    $coolifyBranch = $environment->get('COOLIFY_BRANCH');
    expect($coolifyBranch)->toBe("main");

    $coolifyContainerName = $environment->get('COOLIFY_CONTAINER_NAME');
    expect($coolifyContainerName)->toMatch("/app-[a-z0-9]{24}-[0-9]{12}/");

    $volumes = $app->get('volumes');
    // /etc/nginx
    $fileMount = $volumes->get(0);
    $applicationConfigurationDir = application_configuration_dir();
    expect($fileMount)->toBe("{$applicationConfigurationDir}/{$this->application->uuid}/nginx:/etc/nginx");

    // data:/var/www/html
    $volumeMount = $volumes->get(1);
    expect($volumeMount)->toBe("{$this->application->uuid}_data:/var/www/html");

    $containerName = $app->get('container_name');
    expect($containerName)->toMatch("/app-[a-z0-9]{24}-[0-9]{12}/");

    $labels = $app->get('labels');
    expect($labels)->not->toBeNull();
    expect($labels)->toContain('coolify.managed=true');
    expect($labels)->toContain("coolify.pullRequestId=0");

    $topLevelVolumes = $output->get('volumes');
    expect($topLevelVolumes)->not->toBeNull();
    $firstVolume = $topLevelVolumes->first();
    expect(data_get($firstVolume, 'name'))->toBe("{$this->application->uuid}_data");

    $topLevelNetworks = $output->get('networks');
    expect($topLevelNetworks)->not->toBeNull();
    $defaultNetwork = data_get($topLevelNetworks, 'default');
    expect($defaultNetwork)->not->toBeNull();
    expect(data_get($defaultNetwork, 'name'))->toBe('something');
    expect(data_get($defaultNetwork, 'external'))->toBe(true);

    $noinetNetwork = data_get($topLevelNetworks, 'noinet');
    expect($noinetNetwork)->not->toBeNull();
    expect(data_get($noinetNetwork, 'driver'))->toBe('bridge');
    expect(data_get($noinetNetwork, 'internal'))->toBe(true);

    $serviceNetwork = data_get($topLevelNetworks, "{$this->application->uuid}");
    expect($serviceNetwork)->not->toBeNull();
    expect(data_get($serviceNetwork, 'name'))->toBe("{$this->application->uuid}");
    expect(data_get($serviceNetwork, 'external'))->toBe(true);

});


test('ComposeParsePreviewDeployment', function () {
    $pullRequestId = 1;
    $previewId = 77;
    expect($this->jsonComposeFile)->toBeJson()->ray();

    $output = $this->application->dockerComposeParser(pull_request_id: $pullRequestId, preview_id: $previewId);
    $outputOld = $this->application->parseCompose();
    expect($output)->toBeInstanceOf(Collection::class);
    expect($outputOld)->toBeInstanceOf(Collection::class);

    ray(Yaml::dump($output->toArray(), 10, 2));
    $services = $output->get('services');
    $servicesCount = count($this->composeFile['services']);
    expect($services)->toHaveCount($servicesCount);

    $appNull = $services->get('app');
    expect($appNull)->toBeNull();

    $dbNull = $services->get('db');
    expect($dbNull)->toBeNull();

    $app = $services->get("app-pr-{$pullRequestId}");
    expect($app)->not->toBeNull();

    $db = $services->get("db-pr-{$pullRequestId}");
    expect($db)->not->toBeNull();

    $appDependsOn = $app->get('depends_on');
    expect($appDependsOn)->toContain('db-pr-'.$pullRequestId);

    $dbDependsOn = $db->get('depends_on');

    expect($dbDependsOn->keys()->first())->toContain('app-pr-'.$pullRequestId);
    expect(data_get($dbDependsOn, 'app-pr-'.$pullRequestId.'.condition'))->toBe('service_healthy');


    $environment = $app->get('environment');
    expect($environment)->not->toBeNull();

    $coolifyBranch = $environment->get('COOLIFY_BRANCH');
    expect($coolifyBranch)->toBe("pull/{$pullRequestId}/head");

    $coolifyContainerName = $environment->get('COOLIFY_CONTAINER_NAME');
    expect($coolifyContainerName)->toMatch("/app-[a-z0-9]{24}-pr-{$pullRequestId}/");

    $volumes = $app->get('volumes');
    // /etc/nginx
    $fileMount = $volumes->get(0);
    $applicationConfigurationDir = application_configuration_dir();
    expect($fileMount)->toBe("{$applicationConfigurationDir}/{$this->application->uuid}/nginx-pr-{$pullRequestId}:/etc/nginx");

    // data:/var/www/html
    $volumeMount = $volumes->get(1);
    expect($volumeMount)->toBe("{$this->application->uuid}_data-pr-{$pullRequestId}:/var/www/html");

    $containerName = $app->get('container_name');
    expect($containerName)->toMatch("/app-[a-z0-9]{24}-pr-{$pullRequestId}/");

    $labels = $app->get('labels');
    expect($labels)->not->toBeNull();
    expect($labels)->toContain('coolify.managed=true');
    expect($labels)->toContain("coolify.pullRequestId={$pullRequestId}");

    $topLevelVolumes = $output->get('volumes');
    expect($topLevelVolumes)->not->toBeNull();
    $firstVolume = $topLevelVolumes->first();
    expect(data_get($firstVolume, 'name'))->toBe("{$this->application->uuid}_data-pr-{$pullRequestId}");

    $topLevelNetworks = $output->get('networks');
    expect($topLevelNetworks)->not->toBeNull();
    $defaultNetwork = data_get($topLevelNetworks, 'default');
    expect($defaultNetwork)->not->toBeNull();
    expect(data_get($defaultNetwork, 'name'))->toBe('something');
    expect(data_get($defaultNetwork, 'external'))->toBe(true);

    $noinetNetwork = data_get($topLevelNetworks, 'noinet');
    expect($noinetNetwork)->not->toBeNull();
    expect(data_get($noinetNetwork, 'driver'))->toBe('bridge');
    expect(data_get($noinetNetwork, 'internal'))->toBe(true);

    $serviceNetwork = data_get($topLevelNetworks, "{$this->application->uuid}-{$pullRequestId}");
    expect($serviceNetwork)->not->toBeNull();
    expect(data_get($serviceNetwork, 'name'))->toBe("{$this->application->uuid}-{$pullRequestId}");
    expect(data_get($serviceNetwork, 'external'))->toBe(true);

});

test('DockerBinaryAvailableOnLocalhost', function () {
    $server = Server::find(0);
    $output = instant_remote_process(['docker --version'], $server);
    expect($output)->toContain('Docker version');
});
