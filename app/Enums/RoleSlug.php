<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleSlug: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Staff = 'staff';
    case AdminStaff = 'admin_staff';
}
