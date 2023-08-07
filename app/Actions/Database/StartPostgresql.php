<?php

namespace App\Actions\Database;

use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\StandalonePostgres;

class StartPostgresql
{
    public function __invoke(Server $server, StandalonePostgres $database)
    {
        $activity = remote_process([
            "echo 'Creating required Docker networks...'",
            "echo 'Creating required Docker networks...'",
            "echo 'Creating required Docker networks...'",
            "sleep 4",
            "echo 'Creating required Docker networks...'",
            "echo 'Creating required Docker networks...'",

        ], $server);
        return $activity;
    }
}