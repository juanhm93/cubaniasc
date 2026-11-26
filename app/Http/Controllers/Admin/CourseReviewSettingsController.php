<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesCourseCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCourseReviewSettingsRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * How the students of a course review: normal or with the course's own settings.
 * Sessions already started keep their duration; the new values apply from the next step.
 */
class CourseReviewSettingsController extends Controller
{
    use AuthorizesCourseCompany;

    public function __invoke(UpdateCourseReviewSettingsRequest $request, Course $course): RedirectResponse
    {
        $this->authorizeCourseCompany($request, $course);

        $course->update($request->reviewSettings());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Configuración del repaso guardada.',
        ]);

        return redirect()->route('admin.courses.show', $course);
    }
}
