<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Admin\Models\Staff;

class MdeAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('MDE_ADMIN_EMAIL', 'admin@mde-distribution.fr');
        $password = (string) env('MDE_ADMIN_PASSWORD', 'testing123');

        Staff::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Admin',
                'last_name' => 'MDE',
                'admin' => true,
                'password' => $password,
            ],
        );
    }
}
