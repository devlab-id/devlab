<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends BaseModel
{
    protected static function booted()
    {
        static::created(function ($application) {
            ApplicationSetting::create([
                'application_id' => $application->id,
            ]);
        });
        static::deleting(function ($application) {
            $application->settings()->delete();
            $application->persistentStorages()->delete();
        });
    }


    protected $fillable = [
        'name',
        'project_id',
        'description',
        'git_repository',
        'git_branch',
        'git_full_url',
        'build_pack',
        'environment_id',
        'destination_id',
        'destination_type',
        'source_id',
        'source_type',
        'ports_mappings',
        'ports_exposes',
        'publish_directory',
        'private_key_id'
    ];
    public function publishDirectory(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? '/' . ltrim($value, '/') : null,
        );
    }
    public function gitBranchLocation(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!is_null($this->source?->html_url) && !is_null($this->git_repository) && !is_null($this->git_branch)) {
                    return "{$this->source->html_url}/{$this->git_repository}/tree/{$this->git_branch}";
                }
            }

        );
    }
    public function gitCommits(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!is_null($this->source?->html_url) && !is_null($this->git_repository) && !is_null($this->git_branch)) {
                    return "{$this->source->html_url}/{$this->git_repository}/commits/{$this->git_branch}";
                }
            }

        );
    }
    public function baseDirectory(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => '/' . ltrim($value, '/'),
        );
    }
    public function portsMappings(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === "" ? null : $value,
        );
    }
    public function portsMappingsArray(): Attribute
    {
        return Attribute::make(
            get: fn () =>
            is_null($this->ports_mappings)
                ? []
                : explode(',', $this->ports_mappings),

        );
    }
    public function portsExposesArray(): Attribute
    {
        return Attribute::make(
            get: fn () =>
            is_null($this->ports_exposes)
                ? []
                : explode(',', $this->ports_exposes)
        );
    }
    public function environment_variables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }
    public function runtime_environment_variables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class)->where('key', 'not like', 'NIXPACKS_%');
    }
    public function build_environment_variables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class)->where('is_build_time', true)->where('key', 'not like', 'NIXPACKS_%');
    }
    public function nixpacks_environment_variables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class)->where('key', 'like', 'NIXPACKS_%');
    }
    public function private_key()
    {
        return $this->belongsTo(PrivateKey::class);
    }
    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }
    public function settings()
    {
        return $this->hasOne(ApplicationSetting::class);
    }
    public function destination()
    {
        return $this->morphTo();
    }
    public function source()
    {
        return $this->morphTo();
    }
    public function persistentStorages()
    {
        return $this->morphMany(LocalPersistentVolume::class, 'resource');
    }

    public function deployments()
    {
        return Activity::where('subject_id', $this->id)->where('properties->type', '=', 'deployment')->orderBy('created_at', 'desc')->get();
    }
    public function get_deployment(string $deployment_uuid)
    {
        return Activity::where('subject_id', $this->id)->where('properties->type_uuid', '=', $deployment_uuid)->first();
    }
    public function isDeployable(): bool
    {
        if ($this->settings->is_auto_deploy) {
            return true;
        }
        return false;
    }
    public function deploymentType()
    {
        if (data_get($this, 'source')) {
            return 'source';
        }
        if (data_get($this, 'private_key_id')) {
            return 'deploy_key';
        }
        throw new \Exception('No deployment type found');
    }
}
