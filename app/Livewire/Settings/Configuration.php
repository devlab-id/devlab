<?php

namespace App\Livewire\Settings;

use App\Jobs\ContainerStatusJob;
use App\Models\InstanceSettings as ModelsInstanceSettings;
use App\Models\Server;
use Livewire\Component;
use Spatie\Url\Url;
use Symfony\Component\Yaml\Yaml;

class Configuration extends Component
{
    public ModelsInstanceSettings $settings;
    public bool $do_not_track;
    public bool $is_auto_update_enabled;
    public bool $is_registration_enabled;
    public bool $next_channel;
    protected string $dynamic_config_path = '/data/coolify/proxy/dynamic';
    protected Server $server;

    protected $rules = [
        'settings.fqdn' => 'nullable',
        'settings.resale_license' => 'nullable',
        'settings.public_port_min' => 'required',
        'settings.public_port_max' => 'required',
    ];
    protected $validationAttributes = [
        'settings.fqdn' => 'FQDN',
        'settings.resale_license' => 'Resale License',
        'settings.public_port_min' => 'Public port min',
        'settings.public_port_max' => 'Public port max',
    ];

    public function mount()
    {
        $this->do_not_track = $this->settings->do_not_track;
        $this->is_auto_update_enabled = $this->settings->is_auto_update_enabled;
        $this->is_registration_enabled = $this->settings->is_registration_enabled;
        $this->next_channel = $this->settings->next_channel;
    }

    public function instantSave()
    {
        $this->settings->do_not_track = $this->do_not_track;
        $this->settings->is_auto_update_enabled = $this->is_auto_update_enabled;
        $this->settings->is_registration_enabled = $this->is_registration_enabled;
        if ($this->next_channel) {
            $this->settings->next_channel = false;
            $this->next_channel = false;
        } else {
            $this->settings->next_channel = $this->next_channel;
        }
        $this->settings->save();
        $this->dispatch('success', 'Settings updated!');
    }

    public function submit()
    {
        $this->resetErrorBag();
        if ($this->settings->public_port_min > $this->settings->public_port_max) {
            $this->addError('settings.public_port_min', 'The minimum port must be lower than the maximum port.');
            return;
        }
        $this->validate();
        $this->settings->save();
        $this->server = Server::findOrFail(0);
        $this->setup_instance_fqdn();
        $this->dispatch('success', 'Instance settings updated successfully!');
    }

    private function setup_instance_fqdn()
    {
        $file = "$this->dynamic_config_path/coolify.yaml";
        if (empty($this->settings->fqdn)) {
            instant_remote_process([
                "rm -f $file",
            ], $this->server);
        } else {
            $url = Url::fromString($this->settings->fqdn);
            $host = $url->getHost();
            $schema = $url->getScheme();
            $traefik_dynamic_conf = [
                'http' =>
                [
                    'routers' =>
                    [
                        'coolify-http' =>
                        [
                            'entryPoints' => [
                                0 => 'http',
                            ],
                            'service' => 'coolify',
                            'rule' => "Host(`{$host}`)",
                        ],
                        'coolify-realtime-ws' =>
                        [
                            'entryPoints' => [
                                0 => 'http',
                            ],
                            'service' => 'coolify-realtime',
                            'rule' => "Host(`{$host}`) && PathPrefix(`/realtime/`)",
                        ],
                    ],
                    'services' =>
                    [
                        'coolify' =>
                        [
                            'loadBalancer' =>
                            [
                                'servers' =>
                                [
                                    0 =>
                                    [
                                        'url' => 'http://coolify:80',
                                    ],
                                ],
                            ],
                        ],
                        'coolify-realtime' =>
                        [
                            'loadBalancer' =>
                            [
                                'servers' =>
                                [
                                    0 =>
                                    [
                                        'url' => 'http://coolify-realtime:6001',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            if ($schema === 'https') {
                $traefik_dynamic_conf['http']['routers']['coolify-http']['middlewares'] = [
                    0 => 'redirect-to-https@docker',
                ];
                $traefik_dynamic_conf['http']['routers']['coolify-realtime-wss']['middlewares'] = [
                    0 => 'redirect-to-https@docker',
                ];
                $traefik_dynamic_conf['http']['routers']['coolify-https'] = [
                    'entryPoints' => [
                        0 => 'https',
                    ],
                    'service' => 'coolify',
                    'rule' => "Host(`{$host}`)",
                    'tls' => [
                        'certresolver' => 'letsencrypt',
                    ],
                ];
                $traefik_dynamic_conf['http']['routers']['coolify-realtime-wss'] = [
                    'entryPoints' => [
                        0 => 'https',
                    ],
                    'service' => 'coolify-realtime',
                    'rule' => "Host(`{$host}`) && PathPrefix(`/realtime/`)",
                    'tls' => [
                        'certresolver' => 'letsencrypt',
                    ],
                ];
            }
            $this->save_configuration_to_disk($traefik_dynamic_conf, $file);
        }
    }

    private function save_configuration_to_disk(array $traefik_dynamic_conf, string $file)
    {
        $yaml = Yaml::dump($traefik_dynamic_conf, 12, 2);
        $yaml =
            "# This file is automatically generated by Coolify.\n" .
            "# Do not edit it manually (only if you know what are you doing).\n\n" .
            $yaml;

        $base64 = base64_encode($yaml);
        instant_remote_process([
            "mkdir -p $this->dynamic_config_path",
            "echo '$base64' | base64 -d > $file",
        ], $this->server);

        if (config('app.env') == 'local') {
            ray($yaml);
        }
    }
}
