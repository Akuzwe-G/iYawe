<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateSystemAdministrator extends Command
{
    protected $signature = 'iyawe:admin {email?} {--name=}';

    protected $description = 'Securely create a new platform administrator using a hidden password prompt';

    public function handle(): int
    {
        $email = strtolower($this->argument('email') ?? $this->ask('Email address'));
        $name = $this->option('name') ?? $this->ask('Full name');
        $password = $this->secret('Password (12+ characters, letters and numbers)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
            'name' => ['required', 'string', 'max:180'], 'email' => ['required', 'email', Rule::unique('users')],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($name, $email, $password) {
            $user = User::create(compact('name', 'email', 'password'));
            $user->forceFill(['is_system_admin' => true, 'active' => true])->save();
            Audit::record('platform.administrator.created', null, $user->id);
        });
        $this->info('System Administrator created. Sign in at /login.');

        return self::SUCCESS;
    }
}
