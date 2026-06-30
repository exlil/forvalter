<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Create (or reset the password of) a user. Forvalter has no public
 * registration, so accounts are provisioned with this command — interactively
 * over SSH, or non-interactively (e.g. Forge's command runner) via options.
 */
class CreateUser extends Command
{
    protected $signature = 'app:create-user {--name=} {--email=} {--password=}';

    protected $description = 'Opprett eller oppdater en bruker (ingen offentlig registrering)';

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $name = $this->option('name') ?: ($interactive ? $this->ask('Navn') : null);
        $email = $this->option('email') ?: ($interactive ? $this->ask('E-post') : null);
        $password = $this->option('password') ?: ($interactive ? $this->secret('Passord') : null);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:6'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            $this->line('Bruk: php artisan app:create-user --name="Navn" --email="e@post.no" --password="hemmelig"');

            return self::FAILURE;
        }

        $existed = User::where('email', $email)->exists();
        $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);

        $this->info(($existed ? 'Oppdaterte passord for' : 'Opprettet').' bruker: '.$user->email);

        return self::SUCCESS;
    }
}
