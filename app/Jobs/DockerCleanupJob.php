<?php

namespace App\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DockerCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 500;
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $servers = Server::all();
            foreach ($servers as $server) {
                if (isDev()) {
                    $docker_root_filesystem = "/";
                } else {
                    $docker_root_filesystem = instant_remote_process(['stat --printf=%m $(docker info --format "{{json .DockerRootDir}}" |sed \'s/"//g\')'], $server);
                }
                $disk_usage = json_decode(instant_remote_process(['df -hP | awk \'BEGIN {printf"{\"disks\":["}{if($1=="Filesystem")next;if(a)printf",";printf"{\"mount\":\""$6"\",\"size\":\""$2"\",\"used\":\""$3"\",\"avail\":\""$4"\",\"use%\":\""$5"\"}";a++;}END{print"]}";}\''], $server), true);
                $mount_point = collect(data_get($disk_usage, 'disks'))->where('mount', $docker_root_filesystem)->first();
                if (Str::of(data_get($mount_point, 'use%'))->trim()->replace('%', '')->value() > 60) {
                    continue;
                }
                instant_remote_process(['docker image prune -af'], $server);
                instant_remote_process(['docker container prune -f --filter "label=coolify.managed=true"'], $server);
                instant_remote_process(['docker builder prune -af'], $server);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
