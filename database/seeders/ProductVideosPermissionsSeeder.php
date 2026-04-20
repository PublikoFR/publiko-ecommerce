<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProductVideosPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'manage_product_videos',
            'guard_name' => 'staff',
        ]);

        Role::where('name', 'super_admin')->first()?->givePermissionTo('manage_product_videos');
    }
}
