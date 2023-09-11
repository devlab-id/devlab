<?php

namespace App\Actions\Server;

use App\Models\InstanceSettings;
use App\Models\Server;

class UpdateCoolify
{
    public ?Server $server = null;
    public ?string $latestVersion = null;
    public ?string $currentVersion = null;

    public function __invoke(bool $force)
    {
        try {
            $settings = InstanceSettings::get();
            ray('Running InstanceAutoUpdateJob');
            $localhost_name = 'localhost';
            $this->server = Server::where('name', $localhost_name)->first();
            if (!$this->server) {
                return;
            }
            $this->latestVersion = get_latest_version_of_coolify();
            $this->currentVersion = config('version');
            ray('latest version:' . $this->latestVersion . " current version: " . $this->currentVersion . ' force: ' . $force);
            if ($settings->next_channel) {
                ray('next channel enabled');
                $this->latestVersion = 'next';
            }
            if ($force) {
                $this->update();
            } else {
                if (!$settings->is_auto_update_enabled) {
                    return 'Auto update is disabled';
                }
                if ($this->latestVersion === $this->currentVersion) {
                    return 'Already on latest version';
                }
                if (version_compare($this->latestVersion, $this->currentVersion, '<')) {
                    return 'Latest version is lower than current version?!';
                }
                $this->update();
            }
            send_internal_notification('InstanceAutoUpdateJob done to version: ' . $this->latestVersion . ' from version: ' . $this->currentVersion);
        } catch (\Throwable $e) {
            ray('InstanceAutoUpdateJob failed');
            ray($e->getMessage());
            send_internal_notification('InstanceAutoUpdateJob failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function update()
    {
        if (isDev()) {
            ray("Running update on local docker container. Updating to $this->latestVersion");
            remote_process([
                "sleep 10"
            ], $this->server);
            ray('Update done');
            return;
        } else {
            ray('Running update on production server');
            remote_process([
                "curl -fsSL https://cdn.coollabs.io/coolify/upgrade.sh -o /data/coolify/source/upgrade.sh",
                "bash /data/coolify/source/upgrade.sh $this->latestVersion"
            ], $this->server);
            return;
        }
    }
}
