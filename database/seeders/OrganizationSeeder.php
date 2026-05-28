<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::updateOrCreate(
            ['slug' => 'digicloudify'],
            [
                'name' => 'Digicloudify',
                'plan' => 'enterprise',
                'is_active' => true,
                'settings' => [
                    'currency' => 'INR',
                    'timezone' => 'Asia/Kolkata',
                ]
            ]
        );
    }
}
