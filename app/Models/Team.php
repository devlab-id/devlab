<?php

namespace App\Models;

use App\Notifications\Channels\SendsDiscord;
use App\Notifications\Channels\SendsEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Team extends Model implements SendsDiscord, SendsEmail
{
    use Notifiable;

    protected $guarded = [];
    protected $casts = [
        'personal_team' => 'boolean',
        'smtp_password' => 'encrypted',
        'resend_api_key' => 'encrypted',
    ];

    public function routeNotificationForDiscord()
    {
        return data_get($this, 'discord_webhook_url', null);
    }

    public function routeNotificationForTelegram()
    {
        return [
            "token" => data_get($this, 'telegram_token', null),
            "chat_id" => data_get($this, 'telegram_chat_id', null)
        ];
    }

    public function getRecepients($notification)
    {
        $recipients = data_get($notification, 'emails', null);
        if (is_null($recipients)) {
            $recipients = $this->members()->pluck('email')->toArray();
            return $recipients;
        }
        return explode(',', $recipients);
    }
    public function limits(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (config('coolify.self_hosted') || $this->id === 0) {
                    $subscription = 'self-hosted';
                } else {
                    $subscription = data_get($this, 'subscription');
                    if (is_null($subscription)) {
                        $subscription = 'zero';
                    } else {
                        $subscription = $subscription->type();
                    }
                }
                $serverLimit = config('constants.limits.server')[strtolower($subscription)];
                $sharedEmailEnabled = config('constants.limits.email')[strtolower($subscription)];
                return ['serverLimit' => $serverLimit, 'sharedEmailEnabled' => $sharedEmailEnabled];
            }

        );
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_user', 'team_id', 'user_id')->withPivot('role');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function applications()
    {
        return $this->hasManyThrough(Application::class, Project::class);
    }

    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function isEmpty()
    {
        if ($this->projects()->count() === 0 && $this->servers()->count() === 0 && $this->privateKeys()->count() === 0 && $this->sources()->count() === 0) {
            return true;
        }
        return false;
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function privateKeys()
    {
        return $this->hasMany(PrivateKey::class);
    }

    public function sources()
    {
        $sources = collect([]);
        $github_apps = $this->hasMany(GithubApp::class)->whereisPublic(false)->get();
        $gitlab_apps = $this->hasMany(GitlabApp::class)->whereisPublic(false)->get();
        // $bitbucket_apps = $this->hasMany(BitbucketApp::class)->get();
        $sources = $sources->merge($github_apps)->merge($gitlab_apps);
        return $sources;
    }

    public function s3s()
    {
        return $this->hasMany(S3Storage::class);
    }
}
