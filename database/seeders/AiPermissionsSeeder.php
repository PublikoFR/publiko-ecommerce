<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AiPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate([
            'name' => 'generate_ai_content',
            'guard_name' => 'staff',
        ]);

        Role::where('name', 'super_admin')->first()?->givePermissionTo('generate_ai_content');
    }
}
