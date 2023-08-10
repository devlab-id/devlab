<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

class DatabaseController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    public function configuration()
    {
        $project = session('currentTeam')->load(['projects'])->projects->where('uuid', request()->route('project_uuid'))->first();
        if (!$project) {
            return redirect()->route('dashboard');
        }
        $environment = $project->load(['environments'])->environments->where('name', request()->route('environment_name'))->first()->load(['applications']);
        if (!$environment) {
            return redirect()->route('dashboard');
        }
        $database = $environment->databases->where('uuid', request()->route('database_uuid'))->first();
        if (!$database) {
            return redirect()->route('dashboard');
        }
        return view('project.database.configuration', ['database' => $database]);
    }

    public function backup_logs()
    {
        $backup_uuid = request()->route('backup_uuid');
        $project = session('currentTeam')->load(['projects'])->projects->where('uuid', request()->route('project_uuid'))->first();
        if (!$project) {
            return redirect()->route('dashboard');
        }
        $environment = $project->load(['environments'])->environments->where('name', request()->route('environment_name'))->first()->load(['applications']);
        if (!$environment) {
            return redirect()->route('dashboard');
        }
        $database = $environment->databases->where('uuid', request()->route('database_uuid'))->first();
        if (!$database) {
            return redirect()->route('dashboard');
        }
        $backup = $database->scheduledBackups->where('uuid', $backup_uuid)->first();
        if (!$backup) {
            return redirect()->route('dashboard');
        }
        $backup_executions = collect($backup->executions)->sortByDesc('created_at');
        return view('project.database.backups.logs', ['database' => $database, 'backup' => $backup, 'backup_executions' => $backup_executions]);
    }

    public function backups()
    {
        $project = session('currentTeam')->load(['projects'])->projects->where('uuid', request()->route('project_uuid'))->first();
        if (!$project) {
            return redirect()->route('dashboard');
        }
        $environment = $project->load(['environments'])->environments->where('name', request()->route('environment_name'))->first()->load(['applications']);
        if (!$environment) {
            return redirect()->route('dashboard');
        }
        $database = $environment->databases->where('uuid', request()->route('database_uuid'))->first();
        if (!$database) {
            return redirect()->route('dashboard');
        }
        return view('project.database.backups.all', ['database' => $database]);
    }
}
