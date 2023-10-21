<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\GithubApp;
use App\Models\StandaloneDocker;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Application::create([
            'name' => 'coollabsio/coolify-examples:nodejs-fastify',
            'description' => 'NodeJS Fastify Example',
            'fqdn' => 'http://nodejs.127.0.0.1.sslip.io',
            'repository_project_id' => 603035348,
            'git_repository' => 'coollabsio/coolify-examples',
            'git_branch' => 'nodejs-fastify',
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'environment_id' => 1,
            'destination_id' => 0,
            'destination_type' => StandaloneDocker::class,
            'source_id' => 0,
            'source_type' => GithubApp::class
        ]);
        Application::create([
            'name' => 'coollabsio/coolify-examples:dockerfile',
            'description' => 'Dockerfile Example',
            'fqdn' => 'http://dockerfile.127.0.0.1.sslip.io',
            'repository_project_id' => 603035348,
            'git_repository' => 'coollabsio/coolify-examples',
            'git_branch' => 'dockerfile',
            'build_pack' => 'dockerfile',
            'ports_exposes' => '80',
            'environment_id' => 1,
            'destination_id' => 0,
            'destination_type' => StandaloneDocker::class,
            'source_id' => 0,
            'source_type' => GithubApp::class
        ]);
        Application::create([
            'name' => 'pure-dockerfile',
            'description' => 'Pure Dockerfile Example',
            'fqdn' => 'http://pure-dockerfile.127.0.0.1.sslip.io',
            'git_repository' => 'coollabsio/coolify',
            'git_branch' => 'main',
            'git_commit_sha' => 'HEAD',
            'build_pack' => 'dockerfile',
            'ports_exposes' => '80',
            'environment_id' => 1,
            'destination_id' => 0,
            'destination_type' => StandaloneDocker::class,
            'source_id' => 0,
            'source_type' => GithubApp::class,
            'dockerfile' => 'FROM nginx
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
'
        ]);
    }
}
