<?php

namespace App\Support;

use App\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CompanyAccess
{
    public const PERMISSIONS = ['staff.manage', 'roles.manage', 'locations.manage', 'settings.manage', 'reports.view', 'field.access', 'acknowledgements.create', 'records.search', 'pdf.view', 'swaps.create', 'swaps.view', 'swaps.view-all', 'swaps.update-pending', 'swaps.complete', 'swaps.download', 'reports.export'];

    public static function provision(Company $company): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $admin = Role::firstOrCreate(['company_id' => $company->id, 'name' => 'Company Administrator', 'guard_name' => 'web']);
        $admin->givePermissionTo(self::PERMISSIONS);
        $field = Role::firstOrCreate(['company_id' => $company->id, 'name' => 'Field Staff', 'guard_name' => 'web']);
        $field->givePermissionTo(['field.access', 'acknowledgements.create', 'records.search', 'pdf.view', 'swaps.create', 'swaps.view', 'swaps.update-pending', 'swaps.complete', 'swaps.download']);
    }
}
