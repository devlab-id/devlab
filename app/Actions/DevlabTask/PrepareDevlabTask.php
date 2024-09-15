<?php

namespace App\Actions\DevlabTask;

use App\Data\DevlabTaskArgs;
use App\Enums\ActivityTypes;
use App\Jobs\DevlabTask;
use Spatie\Activitylog\Models\Activity;

/**
 * The initial step to run a `DevlabTask`: a remote SSH process
 * with monitoring/tracking/trace feature. Such thing is made
 * possible using an Activity model and some attributes.
 */
class PrepareDevlabTask
{
    protected Activity $activity;

    protected DevlabTaskArgs $remoteProcessArgs;

    public function __construct(DevlabTaskArgs $remoteProcessArgs)
    {
        $this->remoteProcessArgs = $remoteProcessArgs;

        if ($remoteProcessArgs->model) {
            $properties = $remoteProcessArgs->toArray();
            unset($properties['model']);

            $this->activity = activity()
                ->withProperties($properties)
                ->performedOn($remoteProcessArgs->model)
                ->event($remoteProcessArgs->type)
                ->log('[]');
        } else {
            $this->activity = activity()
                ->withProperties($remoteProcessArgs->toArray())
                ->event($remoteProcessArgs->type)
                ->log('[]');
        }
    }

    public function __invoke(): Activity
    {
        $job = new DevlabTask(
            activity: $this->activity,
            ignore_errors: $this->remoteProcessArgs->ignore_errors,
            call_event_on_finish: $this->remoteProcessArgs->call_event_on_finish,
            call_event_data: $this->remoteProcessArgs->call_event_data,
        );
        if ($this->remoteProcessArgs->type === ActivityTypes::COMMAND->value) {
            ray('Dispatching a high priority job');
            dispatch($job)->onQueue('high');
        } else {
            dispatch($job);
        }
        $this->activity->refresh();

        return $this->activity;
    }
}
