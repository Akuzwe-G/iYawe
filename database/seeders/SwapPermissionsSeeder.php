<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\CompanyAccess;
use Illuminate\Database\Seeder;

class SwapPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->each(fn (Company $company) => CompanyAccess::provision($company));
    }
}
