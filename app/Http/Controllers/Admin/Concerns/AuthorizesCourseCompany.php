<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Course;
use Illuminate\Http\Request;

trait AuthorizesCourseCompany
{
    /**
     * A user tied to a company can only manage that company's courses.
     */
    protected function authorizeCourseCompany(Request $request, Course $course): void
    {
        $companyId = $request->user()?->company_id;

        if ($companyId !== null && (int) $course->company_id !== (int) $companyId) {
            abort(403);
        }
    }
}
