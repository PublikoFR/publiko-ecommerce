<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CmsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'manage_cms_pages',
            'guard_name' => 'staff',
        ]);

        Role::where('name', 'super_admin')->first()?->givePermissionTo('manage_cms_pages');
    }
}
