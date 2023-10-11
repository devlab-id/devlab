<?php

namespace App\Models;

use App\Notifications\Channels\SendsEmail;
use App\Notifications\TransactionalEmails\ResetPassword as TransactionalEmailsResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements SendsEmail
{
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $guarded = [];
    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'force_password_reset' => 'boolean',
        'show_boarding' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::created(function (User $user) {
            $team = [
                'name' => $user->name . "'s Team",
                'personal_team' => true,
                'show_boarding' => true
            ];
            if ($user->id === 0) {
                $team['id'] = 0;
                $team['name'] = 'Root Team';
            }
            $new_team = Team::create($team);
            $user->teams()->attach($new_team, ['role' => 'owner']);
        });
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withPivot('role');
    }

    public function getRecepients($notification)
    {
        return $this->email;
    }

    public function sendVerificationEmail()
    {
        $mail = new MailMessage();
        $url = Url::temporarySignedRoute(
            'verify.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ]
        );
        $mail->view('emails.email-verification', [
            'url' => $url,
        ]);
        $mail->subject('Coolify Cloud: Verify your email.');
        send_user_an_email($mail, $this->email);
    }
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new TransactionalEmailsResetPassword($token));
    }

    public function isAdmin()
    {
        return data_get($this->pivot, 'role') === 'admin' || data_get($this->pivot, 'role') === 'owner';
    }

    public function isAdminFromSession()
    {
        if (auth()->user()->id === 0) {
            return true;
        }
        $teams = $this->teams()->get();

        $is_part_of_root_team = $teams->where('id', 0)->first();
        $is_admin_of_root_team = $is_part_of_root_team &&
            ($is_part_of_root_team->pivot->role === 'admin' || $is_part_of_root_team->pivot->role === 'owner');

        if ($is_part_of_root_team && $is_admin_of_root_team) {
            return true;
        }
        $team = $teams->where('id', session('currentTeam')->id)->first();
        $role = data_get($team, 'pivot.role');
        return $role === 'admin' || $role === 'owner';
    }

    public function isInstanceAdmin()
    {
        $found_root_team = auth()->user()->teams->filter(function ($team) {
            if ($team->id == 0) {
                return true;
            }
            return false;
        });
        return $found_root_team->count() > 0;
    }

    public function currentTeam()
    {
        return Cache::remember('team:' . auth()->user()->id, 3600, function () {
            if (is_null(data_get(session('currentTeam'), 'id'))) {
                return auth()->user()->teams[0];
            }
            return Team::find(session('currentTeam')->id);
        });
    }

    public function otherTeams()
    {
        return auth()->user()->teams->filter(function ($team) {
            return $team->id != currentTeam()->id;
        });
    }

    public function role()
    {
        return session('currentTeam')->pivot->role;
    }
}
