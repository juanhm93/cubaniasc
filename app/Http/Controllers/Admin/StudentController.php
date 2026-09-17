<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminEnrollmentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * Paginated list of enrollments (alumnos por curso) with filters.
     */
    public function index(Request $request): Response
    {
        $companyId = $request->user()?->company_id;

        $query = Enrollment::query()
            ->with([
                'student:id,name,email',
                'course' => fn ($q) => $q->select('id', 'level_id', 'company_id')->with('level:id,name'),
            ])
            ->whereHas('student')
            ->whereHas('course', function ($q) use ($companyId): void {
                if ($companyId !== null) {
                    $q->where('company_id', $companyId);
                }
            });

        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->query('course_id'));
        }

        if ($request->filled('level_id')) {
            $query->whereHas('course', fn ($q) => $q->where('level_id', (int) $request->query('level_id')));
        }

        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->query('search'), '%_\\').'%';
            $query->whereHas('student', function ($q) use ($term): void {
                $q->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            });
        }

        $status = $request->query('status');

        if ($status === 'active') {
            $query->where('status', EnrollmentStatus::Active);
        } elseif ($status === 'inactive') {
            $query->whereIn('status', [
                EnrollmentStatus::Completed,
                EnrollmentStatus::Withdrawn,
            ]);
        }

        $paginator = $query
            ->orderByDesc('enrollments.id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Enrollment $e): array {
                return [
                    'id' => $e->id,
                    'student_id' => $e->student_id,
                    'student_name' => $e->student?->name ?? '',
                    'student_email' => $e->student?->email ?? '',
                    'course_id' => $e->course_id,
                    'course_label' => $e->course?->level?->name ?? ('Curso #'.$e->course_id),
                    'level_name' => $e->course?->level?->name ?? '',
                    'status' => $e->status->value,
                    'status_label' => match ($e->status) {
                        EnrollmentStatus::Active => 'Activo',
                        EnrollmentStatus::Completed => 'Completado',
                        EnrollmentStatus::Withdrawn => 'Baja',
                    },
                ];
            });

        $courseOptions = Course::query()
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->with('level:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Course $c): array => [
                'id' => $c->id,
                'label' => $c->level?->name ?? ('Curso #'.$c->id),
            ])
            ->values()
            ->all();

        $levelOptions = Level::query()
            ->orderBy('dance_type_id')
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn (Level $l): array => [
                'id' => $l->id,
                'name' => $l->name,
            ])
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('admin/students/index', [
            'enrollments' => $paginator,
            'filters' => [
                'course_id' => $request->query('course_id'),
                'level_id' => $request->query('level_id'),
                'status' => $request->query('status'),
                'search' => $request->query('search'),
            ],
            'courseOptions' => $courseOptions,
            'levelOptions' => $levelOptions,
            'canDeleteStudents' => $user !== null && ($user->isAdmin() || $user->isOwner()),
        ]);
    }

    /**
     * Form to enroll an existing student in a course.
     */
    public function enroll(Request $request): Response
    {
        $companyId = $request->user()?->company_id;

        $courses = Course::query()
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->with('level:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Course $c): array => [
                'id' => $c->id,
                'label' => $c->level?->name ?? ('Curso #'.$c->id),
            ])
            ->values()
            ->all();

        $students = Student::query()
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email'])
            ->map(fn (Student $s): array => [
                'id' => $s->id,
                'label' => $s->name.($s->email ? ' · '.$s->email : ''),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/students/enroll', [
            'courses' => $courses,
            'students' => $students,
        ]);
    }

    /**
     * Store a new enrollment for an existing student.
     */
    public function storeEnrollment(StoreAdminEnrollmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Enrollment::query()->create([
            'course_id' => $data['course_id'],
            'student_id' => $data['student_id'],
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);

        return redirect()->route('admin.students.index');
    }

    /**
     * Show student profile for admin review and editing.
     */
    public function show(Request $request, Student $student): Response
    {
        $student->load([
            'enrollments' => fn ($query) => $query->with([
                'course' => fn ($q) => $q->with('level:id,name'),
            ]),
        ]);

        $user = $request->user();

        return Inertia::render('admin/students/show', [
            'student' => $student,
            'canUpdateEmail' => $user !== null && $user->can('updateEmail', $student),
        ]);
    }

    /**
     * Update student profile fields. Email is restricted to owners and admins.
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->studentAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Datos del alumno actualizados.',
        ]);

        return redirect()->route('admin.students.show', $student);
    }

    /**
     * Soft-delete a student. Restricted to owners and admins.
     */
    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $request->user()?->can('delete', $student) || abort(403);

        $student->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Alumno eliminado.',
        ]);

        return redirect()->route('admin.students.index');
    }
}
