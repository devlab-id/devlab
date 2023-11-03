<?php
/**
 * @label Send Email
 * @description Send email to all users
 */

use Illuminate\Notifications\Messages\MailMessage;

set_transanctional_email_settings();

$users = User::all();
foreach ($users as $user) {
  Mail::send([], [], function ($message) use ($user) {
    $message
      ->to($user->email)
      ->subject("Testing")
      ->text(
        <<<EOF
Hello,

Welcome to Coolify Cloud.
Here is your user id: $user->id

EOF
      );
  });
}
