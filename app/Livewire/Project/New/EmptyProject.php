<?php

namespace App\Livewire\Project\New;

use App\Models\Project;
use Livewire\Component;

class EmptyProject extends Component
{
    public function createEmptyProject()
    {
        $project = Project::create([
            'name' => generate_random_name(),
            'team_id' => currentTeam()->id,
        ]);
        return $this->redirectRoute('project.show', ['project_uuid' => $project->uuid, 'environment_name' => 'production'], navigate: false);
    }
}
