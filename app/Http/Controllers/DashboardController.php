<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\PlatformAbility;
use App\Models\Course;
use App\Models\Student;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();

        $activeStudentsCount = Student::query()
            ->whereHas('enrollments', function ($query): void {
                $query->where('status', EnrollmentStatus::Active)
                    ->whereHas('course', function ($q): void {
                        $q->where('is_active', true);
                    });
            })
            ->count();

        $activeCoursesCount = Course::query()
            ->where('is_active', true)
            ->count();

        return Inertia::render('dashboard', [
            'stats' => [
                'activeStudents' => $activeStudentsCount,
                'activeCourses' => $activeCoursesCount,
            ],
            'staffExample' => [
                'professors' => 3,
                'administrativeStaff' => 1,
                'administrators' => 2,
            ],
            'canManageCourses' => $user !== null && $user->hasAbility(PlatformAbility::Courses),
        ]);
    }
}
