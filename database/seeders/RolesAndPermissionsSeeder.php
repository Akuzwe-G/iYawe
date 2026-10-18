<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\CompanyAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (CompanyAccess::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Company::query()->each(function (Company $company): void {
            CompanyAccess::provision($company);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
