<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || ! str_starts_with((string) config('database.connections.'.config('database.default').'.database'), 'iyawe_m4_test_')) {
            throw new \RuntimeException('Acceptance fixtures require an isolated iyawe_m4_test_ database and APP_ENV=testing.');
        }
        $password = env('IYAWE_ACCEPTANCE_PASSWORD');
        if (! is_string($password) || strlen($password) < 16) {
            throw new \RuntimeException('Supply a temporary IYAWE_ACCEPTANCE_PASSWORD of at least 16 characters through the test process environment.');
        }
        foreach (['a', 'b'] as $slug) {
            $company = Company::firstOrCreate(['slug' => 'acceptance-'.$slug], ['name' => 'Fictional Company '.strtoupper($slug), 'status' => 'active']);
            CompanyAccess::provision($company);
            foreach (['admin' => 'Company Administrator', 'field' => 'Field Staff'] as $name => $roleName) {
                $user = User::updateOrCreate(['email' => $name.'-'.$slug.'@example.test'], ['name' => 'Fictional '.$name.' '.strtoupper($slug), 'password' => $password, 'active' => true, 'locale' => 'en']);
                $company->users()->syncWithoutDetaching([$user->id => ['active' => true, 'pending' => false]]);
                setPermissionsTeamId($company->id);
                $user->syncRoles([Role::where('company_id', $company->id)->where('name', $roleName)->firstOrFail()]);
                setPermissionsTeamId(null);
            }
        }
        User::updateOrCreate(['email' => 'system@example.test'], ['name' => 'Fictional System Admin', 'password' => $password, 'active' => true, 'is_system_admin' => true]);
    }
}
