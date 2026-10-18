<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class SystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('iyawe.system_admin.name');
        $email = strtolower(trim((string) config('iyawe.system_admin.email')));
        $password = config('iyawe.system_admin.password');

        if ($email === '' && ($password === null || $password === '')) {
            $this->command?->info('System Administrator skipped: no environment credentials configured.');

            return;
        }

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:254']])->fails()) {
            throw new RuntimeException('Configure a valid IYAWE_SYSTEM_ADMIN_EMAIL before seeding.');
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $existing = User::where('email', $email)->lockForUpdate()->first();

            if ($existing) {
                if (! $existing->is_system_admin) {
                    throw new RuntimeException('The configured email is already registered to a non-platform account. Choose a separate System Administrator email.');
                }

                $this->command?->info('System Administrator already exists; account and password preserved.');

                return;
            }

            $validator = Validator::make(['name' => $name, 'password' => $password], [
                'name' => ['required', 'string', 'max:180'],
                'password' => ['required', 'string', Password::min(12)->letters()->numbers()],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException('Configure IYAWE_SYSTEM_ADMIN_NAME and IYAWE_SYSTEM_ADMIN_PASSWORD (at least 12 characters with letters and numbers).');
            }

            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['is_system_admin' => true, 'active' => true])->save();
            Audit::record('platform.administrator.seeded', null, $user->id);
            $this->command?->info('iYawe System Administrator created. Sign in at /login.');
        });
    }
}
