<?php

use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Visus\Cuid2\Cuid2;

function generate_database_name(string $type): string
{
    $cuid = new Cuid2(7);
    return $type . '-database-' . $cuid;
}

function create_standalone_postgresql($environment_id, $destination_uuid): StandalonePostgresql
{
    // TODO: If another type of destination is added, this will need to be updated.
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->first();
    if (!$destination) {
        throw new Exception('Destination not found');
    }
    return StandalonePostgresql::create([
        'name' => generate_database_name('postgresql'),
        'postgres_password' => \Illuminate\Support\Str::password(symbols: false),
        'environment_id' => $environment_id,
        'destination_id' => $destination->id,
        'destination_type' => $destination->getMorphClass(),
    ]);
}

function create_standalone_redis($environment_id, $destination_uuid): StandaloneRedis
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->first();
    if (!$destination) {
        throw new Exception('Destination not found');
    }
    return StandaloneRedis::create([
        'name' => generate_database_name('redis'),
        'redis_password' => \Illuminate\Support\Str::password(symbols: false),
        'environment_id' => $environment_id,
        'destination_id' => $destination->id,
        'destination_type' => $destination->getMorphClass(),
    ]);
}

function create_standalone_mongodb($environment_id, $destination_uuid): StandaloneMongodb
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->first();
    if (!$destination) {
        throw new Exception('Destination not found');
    }
    return StandaloneMongodb::create([
        'name' => generate_database_name('mongodb'),
        'mongo_initdb_root_password' => \Illuminate\Support\Str::password(symbols: false),
        'environment_id' => $environment_id,
        'destination_id' => $destination->id,
        'destination_type' => $destination->getMorphClass(),
    ]);
}
function create_standalone_mysql($environment_id, $destination_uuid): StandaloneMysql
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->first();
    if (!$destination) {
        throw new Exception('Destination not found');
    }
    return StandaloneMysql::create([
        'name' => generate_database_name('mysql'),
        'mysql_root_password' => \Illuminate\Support\Str::password(symbols: false),
        'mysql_password' => \Illuminate\Support\Str::password(symbols: false),
        'environment_id' => $environment_id,
        'destination_id' => $destination->id,
        'destination_type' => $destination->getMorphClass(),
    ]);
}
function create_standalone_mariadb($environment_id, $destination_uuid): StandaloneMariadb
{
    $destination = StandaloneDocker::where('uuid', $destination_uuid)->first();
    if (!$destination) {
        throw new Exception('Destination not found');
    }
    return StandaloneMariadb::create([
        'name' => generate_database_name('mariadb'),
        'mariadb_root_password' => \Illuminate\Support\Str::password(symbols: false),
        'mariadb_password' => \Illuminate\Support\Str::password(symbols: false),
        'environment_id' => $environment_id,
        'destination_id' => $destination->id,
        'destination_type' => $destination->getMorphClass(),
    ]);
}

/**
 * Delete file locally on the filesystem.
 * @param string $filename
 * @param Server $server
 * @return void
 */
function delete_backup_locally(string | null $filename, Server $server): void
{
    if (empty($filename)) {
        return;
    }
    instant_remote_process(["rm -f \"{$filename}\""], $server, throwError: false);
}
