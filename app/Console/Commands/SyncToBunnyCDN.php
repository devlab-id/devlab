<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class SyncToBunnyCDN extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-to-bunny-cdn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bunny_cdn = "https://coolify-cdn.b-cdn.net/";
        $bunny_cdn_path = "files";
        $bunny_cdn_storage_name = "coolify-cdn";

        $parent_dir = realpath(dirname(__FILE__) . '/../../..');

        $compose_file = "docker-compose.yml";
        $compose_file_prod = "docker-compose.prod.yml";
        $upgrade_script = "upgrade.sh";
        $production_env = ".env.production";

        PendingRequest::macro('storage', function ($file) {
            $headers = [
                'AccessKey' => env('BUNNY_STORAGE_API_KEY'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/octet-stream'
            ];
            $fileStream = fopen($file, "r");
            $file = fread($fileStream, filesize($file));
            return PendingRequest::baseUrl('https://storage.bunnycdn.com')->withHeaders($headers)->withBody($file)->throw();
        });
        try {
            Http::pool(fn (Pool $pool) => [
                $pool->storage(file: "$parent_dir/$compose_file")->put("/$bunny_cdn_storage_name/$bunny_cdn_path/$compose_file"),
                $pool->storage(file: "$parent_dir/$compose_file_prod")->put("/$bunny_cdn_storage_name/$bunny_cdn_path/$compose_file_prod"),
                $pool->storage(file: "$parent_dir/scripts/$upgrade_script")->put("/$bunny_cdn_storage_name/$bunny_cdn_path/$upgrade_script"),
                $pool->storage(file: "$parent_dir/$production_env")->put("/$bunny_cdn_storage_name/$bunny_cdn_path/$production_env"),
            ]);

            $res = Http::withHeaders([
                'AccessKey' => env('BUNNY_API_KEY'),
                'Accept' => 'application/json',
            ])->get('https://api.bunny.net/purge', [
                "url" => e("$bunny_cdn/$bunny_cdn_path/$compose_file"),
                "url" => e("$bunny_cdn/$bunny_cdn_path/$compose_file_prod"),
                "url" => e("$bunny_cdn/$bunny_cdn_path/$upgrade_script"),
                "url" => e("$bunny_cdn/$bunny_cdn_path/$production_env")
            ]);
            if ($res->ok()) {
                echo 'All files uploaded & purged...';
            }
        } catch (\Exception $e) {
            echo 'Something went wrong.';
            echo $e->getMessage();
        }
    }
}
