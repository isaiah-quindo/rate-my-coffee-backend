<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full access to all features',
            ],
            [
                'name' => 'Shop Owner',
                'slug' => 'shop_owner',
                'description' => 'Can manage their own coffee shops',
            ],
            [
                'name' => 'Moderator',
                'slug' => 'moderator',
                'description' => 'Can moderate reviews and coffee shop listings',
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'description' => 'Regular user with basic privileges',
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
