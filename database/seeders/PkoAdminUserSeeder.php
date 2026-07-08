<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Admin\Models\Staff;

class PkoAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@weklo.fr');
        $password = (string) env('ADMIN_PASSWORD', 'testing123');

        Staff::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Admin',
                'last_name' => 'Weklo',
                'admin' => true,
                'password' => $password,
            ],
        );
    }
}
