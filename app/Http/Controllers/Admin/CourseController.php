<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\ToggleCourseLevelContentRequest;
use App\Http\Requests\UpdateAdminCourseRequest;
use App\Http\Requests\UpsertCourseAttendanceRequest;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseLevelContentProgress;
use App\Models\CourseScheduleSlot;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * List academy courses (scoped to admin company when set).
     */
    public function index(Request $request): Response
    {
        $companyId = $request->user()?->company_id;

        $courses = Course::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->with([
                'level:id,name,slug',
                'place:id,name',
                'teacher:id,name',
                'scheduleSlots:id,course_id,weekday,starts_at,ends_at,sort_order',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'is_active' => $course->is_active,
                'price' => (string) $course->price,
                'level_name' => $course->level?->name ?? '',
                'place_name' => $course->place?->name ?? '',
                'teacher_name' => $course->teacher?->name ?? '',
                'schedule_summary' => self::formatScheduleSummary($course->scheduleSlots),
            ]);

        return Inertia::render('admin/courses/index', [
            'courses' => $courses,
        ]);
    }

    /**
     * Form to create a course with flexible weekly slots.
     */
    public function create(Request $request): Response
    {
        $companyId = $request->user()?->company_id;

        $levels = Level::query()
            ->with('danceType:id,name')
            ->orderBy('dance_type_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'dance_type_id'])
            ->map(fn (Level $level): array => [
                'id' => $level->id,
                'name' => $level->danceType !== null
                    ? $level->danceType->name.' — '.$level->name
                    : $level->name,
            ]);

        $places = Place::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $teachers = User::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/courses/create', [
            'levels' => $levels,
            'places' => $places,
            'teachers' => $teachers,
        ]);
    }

    /**
     * Persist new course and schedule slots.
     */
    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $companyId = $request->user()?->company_id;

        if ($companyId === null) {
            abort(403, 'Tu usuario debe estar asociado a una academia.');
        }

        $slots = $request->validatedSlots();

        $data = $request->validated();

        $course = DB::transaction(function () use ($data, $companyId, $slots): Course {
            $course = Course::query()->create([
                'level_id' => $data['level_id'],
                'schedule_id' => null,
                'price' => $data['price'],
                'company_id' => $companyId,
                'place_id' => $data['place_id'],
                'user_id' => $data['user_id'],
                'is_active' => (bool) $data['is_active'],
            ]);

            foreach ($slots as $index => $slot) {
                CourseScheduleSlot::query()->create([
                    'course_id' => $course->id,
                    'weekday' => $slot['weekday'],
                    'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'],
                    'sort_order' => $index,
                ]);
            }

            return $course;
        });

        return redirect()->route('admin.courses.show', $course);
    }

    /**
     * Course workspace: students + attendance, figures + level progression.
     */
    public function show(Request $request, Course $course): Response
    {
        $this->authorizeCourseCompany($request, $course);

        $course->load([
            'level:id,name,slug,sort_order,dance_type_id',
            'schedule:id,days',
            'scheduleSlots',
            'place:id,name',
            'teacher:id,name',
            'courseLevels.level:id,name,slug,sort_order',
        ]);

        $sessions = $course->courseSessions()
            ->orderByDesc('session_date')
            ->limit(80)
            ->get(['id', 'course_id', 'session_date', 'starts_at', 'ends_at']);

        $sessionIdQuery = $request->query('session_id');
        $selectedSessionId = null;

        if (is_numeric($sessionIdQuery)) {
            $candidate = $sessions->firstWhere('id', (int) $sessionIdQuery);
            if ($candidate !== null) {
                $selectedSessionId = $candidate->id;
            }
        }

        if ($selectedSessionId === null) {
            $today = now()->toDateString();
            $selectedSessionId = $sessions->first(function (CourseSession $s) use ($today): bool {
                return $s->session_date->toDateString() <= $today;
            })?->id ?? $sessions->first()?->id;
        }

        $enrollments = $course->enrollments()
            ->where('status', EnrollmentStatus::Active)
            ->with(['student:id,name,email'])
            ->orderBy('id')
            ->get();

        $studentIds = $enrollments->pluck('student_id')->all();
        $sessionIds = $sessions->pluck('id')->all();

        $attendanceRows = Attendance::query()
            ->whereIn('course_session_id', $sessionIds)
            ->whereIn('student_id', $studentIds)
            ->get(['course_session_id', 'student_id', 'status']);

        $attendanceMap = [];

        foreach ($attendanceRows as $row) {
            $sid = (int) $row->student_id;
            $sessId = (int) $row->course_session_id;

            if (! isset($attendanceMap[$sid])) {
                $attendanceMap[$sid] = [];
            }

            $attendanceMap[$sid][$sessId] = $row->status instanceof \BackedEnum
                ? $row->status->value
                : (string) $row->status;
        }

        $levelContents = LevelContent::query()
            ->where('level_id', $course->level_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'sort_order', 'video_url']);

        $contentIds = $levelContents->pluck('id')->all();

        $progressRows = CourseLevelContentProgress::query()
            ->where('course_id', $course->id)
            ->whereIn('level_content_id', $contentIds)
            ->get(['level_content_id']);

        $courseProgress = [];

        foreach ($progressRows as $row) {
            $courseProgress[(int) $row->level_content_id] = true;
        }

        $nextLevel = null;

        if ($course->level !== null) {
            $nextLevel = Level::query()
                ->where('dance_type_id', $course->level->dance_type_id)
                ->where('sort_order', '>', $course->level->sort_order)
                ->orderBy('sort_order')
                ->first(['id', 'name', 'slug']);
        }

        $levelsPath = $course->courseLevels->map(fn (CourseLevel $cl): array => [
            'level_id' => $cl->level_id,
            'name' => $cl->level?->name ?? '',
            'sort_order' => $cl->sort_order,
        ])->values()->all();

        $sessionsPayload = $sessions->map(fn (CourseSession $s): array => [
            'id' => $s->id,
            'session_date' => $s->session_date->toDateString(),
            'starts_at' => $s->starts_at,
            'ends_at' => $s->ends_at,
        ])->values()->all();

        $enrollmentPayload = $enrollments->map(function (Enrollment $enrollment) use ($attendanceMap): array {
            $sid = (int) $enrollment->student_id;
            $eid = (int) $enrollment->id;

            return [
                'enrollment_id' => $eid,
                'student_id' => $sid,
                'student_name' => $enrollment->student?->name ?? '',
                'student_email' => $enrollment->student?->email ?? '',
                'attendance_by_session' => $attendanceMap[$sid] ?? [],
            ];
        })->values()->all();

        $companyId = $request->user()?->company_id;

        $places = Place::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($course->place_id !== null && ! $places->contains('id', $course->place_id)) {
            $extraPlace = Place::query()->find($course->place_id, ['id', 'name']);
            if ($extraPlace !== null) {
                $places = $places->prepend($extraPlace)->unique('id')->sortBy('name')->values();
            }
        }

        $teachers = User::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($course->user_id !== null && ! $teachers->contains('id', $course->user_id)) {
            $extraTeacher = User::query()->find($course->user_id, ['id', 'name']);
            if ($extraTeacher !== null) {
                $teachers = $teachers->prepend($extraTeacher)->unique('id')->sortBy('name')->values();
            }
        }

        return Inertia::render('admin/courses/show', [
            'course' => [
                'id' => $course->id,
                'is_active' => $course->is_active,
                'price' => (string) $course->price,
                'level' => $course->level ? [
                    'id' => $course->level->id,
                    'name' => $course->level->name,
                    'slug' => $course->level->slug,
                ] : null,
                'schedule' => $course->schedule,
                'place' => $course->place ? ['id' => $course->place->id, 'name' => $course->place->name] : null,
                'teacher' => $course->teacher ? ['id' => $course->teacher->id, 'name' => $course->teacher->name] : null,
                'schedule_slots' => $course->scheduleSlots->map(fn (CourseScheduleSlot $slot): array => [
                    'weekday' => $slot->weekday,
                    'weekday_label' => self::weekdayShortEs((int) $slot->weekday),
                    'starts_at' => self::formatTimeSlot($slot->starts_at),
                    'ends_at' => self::formatTimeSlot($slot->ends_at),
                ])->values()->all(),
                'schedule_summary' => self::formatScheduleSummary($course->scheduleSlots),
            ],
            'levelsPath' => $levelsPath,
            'nextLevel' => $nextLevel ? ['id' => $nextLevel->id, 'name' => $nextLevel->name] : null,
            'sessions' => $sessionsPayload,
            'selectedSessionId' => $selectedSessionId,
            'enrollments' => $enrollmentPayload,
            'courseProgress' => $courseProgress,
            'levelContents' => $levelContents->map(fn (LevelContent $lc): array => [
                'id' => $lc->id,
                'name' => $lc->name,
                'sort_order' => $lc->sort_order,
                'video_url' => $lc->video_url,
            ]),
            'places' => $places->map(fn (Place $p): array => [
                'id' => $p->id,
                'name' => $p->name,
            ]),
            'teachers' => $teachers->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
            ]),
        ]);
    }

    public function update(UpdateAdminCourseRequest $request, Course $course): RedirectResponse
    {
        $this->authorizeCourseCompany($request, $course);

        $data = $request->validated();

        $course->update([
            'is_active' => (bool) $data['is_active'],
            'user_id' => $data['user_id'],
            'place_id' => $data['place_id'],
        ]);

        return redirect()->route('admin.courses.show', $course);
    }

    public function advanceLevel(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourseCompany($request, $course);

        $course->load('level');

        if ($course->level === null) {
            return redirect()->route('admin.courses.show', $course)->withErrors([
                'level' => 'El curso no tiene nivel asignado.',
            ]);
        }

        $next = Level::query()
            ->where('dance_type_id', $course->level->dance_type_id)
            ->where('sort_order', '>', $course->level->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next === null) {
            return redirect()->route('admin.courses.show', $course)->withErrors([
                'level' => 'No hay un nivel siguiente en esta modalidad.',
            ]);
        }

        DB::transaction(function () use ($course, $next): void {
            $maxSort = (int) CourseLevel::query()
                ->where('course_id', $course->id)
                ->max('sort_order');

            CourseLevel::query()->firstOrCreate(
                [
                    'course_id' => $course->id,
                    'level_id' => $next->id,
                ],
                ['sort_order' => $maxSort + 1],
            );

            $course->level_id = $next->id;
            $course->save();
        });

        return redirect()->route('admin.courses.show', $course);
    }

    public function storeAttendance(
        UpsertCourseAttendanceRequest $request,
        Course $course,
        CourseSession $courseSession,
    ): RedirectResponse {
        $this->authorizeCourseCompany($request, $course);

        if ($courseSession->course_id !== $course->id) {
            abort(404);
        }

        $data = $request->validated();

        Attendance::query()->updateOrCreate(
            [
                'course_session_id' => $courseSession->id,
                'student_id' => $data['student_id'],
            ],
            [
                'status' => AttendanceStatus::from($data['status']),
            ],
        );

        return redirect()->to(
            route('admin.courses.show', $course).'?session_id='.$courseSession->id
        );
    }

    public function toggleCourseLevelContent(
        ToggleCourseLevelContentRequest $request,
        Course $course,
    ): RedirectResponse {
        $this->authorizeCourseCompany($request, $course);

        $levelContentId = (int) $request->validated('level_content_id');

        $belongsToCurrentLevel = LevelContent::query()
            ->where('id', $levelContentId)
            ->where('level_id', $course->level_id)
            ->exists();

        if (! $belongsToCurrentLevel) {
            return redirect()->route('admin.courses.show', $course)->withErrors([
                'level_content_id' => 'La figura no pertenece al nivel actual del curso.',
            ]);
        }

        $existing = CourseLevelContentProgress::query()
            ->where('course_id', $course->id)
            ->where('level_content_id', $levelContentId)
            ->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            CourseLevelContentProgress::query()->create([
                'course_id' => $course->id,
                'level_content_id' => $levelContentId,
                'completed_at' => now(),
            ]);
        }

        return redirect()->route('admin.courses.show', $course);
    }

    private function authorizeCourseCompany(Request $request, Course $course): void
    {
        $companyId = $request->user()?->company_id;

        if ($companyId !== null && (int) $course->company_id !== (int) $companyId) {
            abort(403);
        }
    }

    /**
     * @param  Collection<int, CourseScheduleSlot>  $slots
     */
    private static function formatScheduleSummary(Collection $slots): string
    {
        if ($slots->isEmpty()) {
            return '';
        }

        return $slots
            ->sortBy(fn (CourseScheduleSlot $s): array => [$s->sort_order, $s->weekday])
            ->map(function (CourseScheduleSlot $slot): string {
                $day = self::weekdayShortEs((int) $slot->weekday);
                $from = self::formatTimeSlot($slot->starts_at);
                $to = self::formatTimeSlot($slot->ends_at);

                return "{$day} {$from}–{$to}";
            })
            ->implode(' · ');
    }

    private static function weekdayShortEs(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            7 => 'Dom',
            default => '?',
        };
    }

    private static function formatTimeSlot(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $str = is_string($value) ? $value : (string) $value;

        if (preg_match('/^\d{2}:\d{2}/', $str, $m)) {
            return substr($m[0], 0, 5);
        }

        return $str;
    }
}
