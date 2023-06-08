<?php

namespace App\Http\Controllers;

use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function dashboard()
    {
        $projects = Project::ownedByCurrentTeam()->get();
        $servers = Server::ownedByCurrentTeam()->get();

        $resources = 0;
        foreach ($projects as $project) {
            $resources += $project->applications->count();
        }

        return view('dashboard', [
            'servers' => $servers->count(),
            'projects' => $projects->count(),
            'resources' => $resources,
        ]);
    }
    public function settings()
    {
        if (auth()->user()->isInstanceAdmin()) {
            $settings = InstanceSettings::get();
            return view('settings.configuration', [
                'settings' => $settings
            ]);
        } else {
            return redirect()->route('dashboard');
        }
    }
    public function emails()
    {
        if (auth()->user()->isInstanceAdmin()) {
            $settings = InstanceSettings::get();
            return view('settings.emails', [
                'settings' => $settings
            ]);
        } else {
            return redirect()->route('dashboard');
        }
    }
}
