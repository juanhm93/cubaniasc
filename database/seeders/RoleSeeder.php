<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full platform access', 'is_system' => true],
            ['name' => 'Teacher', 'slug' => 'teacher', 'description' => 'Academic and class management', 'is_system' => true],
            ['name' => 'Staff', 'slug' => 'staff', 'description' => 'Operational support access', 'is_system' => true],
            ['name' => 'Admin Staff', 'slug' => 'admin_staff', 'description' => 'Administrative staff access', 'is_system' => true],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
