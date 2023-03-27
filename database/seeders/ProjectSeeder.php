<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $root_team = Team::find(1);
        Project::create([
            'id' => 1,
            'name' => "My first project",
            'description' => "This is a test project in development",
            'team_id' => $root_team->id,
        ]);
    }
}
