<?php

use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\OneTimeSessionController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PreRegistrationEnrollmentController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Api\DanceTypeController as ApiDanceTypeController;
use App\Http\Controllers\Api\LevelContentController;
use App\Http\Controllers\Api\LevelController as ApiLevelController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\PreRegistrationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

Route::get('pre-inscripcion', [PreRegistrationController::class, 'create'])
    ->name('pre-registration.create');
Route::post('pre-inscripcion', [PreRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('pre-registration.store');

Route::middleware(['auth'])->group(function () {
    Route::get('account/pending', function () {
        $user = request()->user();

        if ($user !== null && $user->status !== 'pending') {
            return redirect()->route('dashboard');
        }

        return Inertia::render('account/pending');
    })->name('account.pending');
});

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('admin')->group(function () {
        Route::get('contenido', [ContentController::class, 'index'])->name('content.index');
        Route::get('contenido/{danceType}', [ContentController::class, 'show'])->name('content.show');
        Route::get('contenido/{danceType}/niveles/{level}', [ContentController::class, 'level'])
            ->scopeBindings()
            ->name('content.levels.show');

        Route::get('levels', [LevelController::class, 'index'])->name('levels');
        Route::get('levels/{level}', [LevelController::class, 'show'])->name('levels.show');

        Route::get('admin/payments', [PaymentController::class, 'index'])->name('admin.payments.index');
        Route::post('admin/payments', [PaymentController::class, 'store'])->name('admin.payments.store');
        Route::get('admin/students/enroll', [StudentController::class, 'enroll'])->name('admin.students.enroll');
        Route::post('admin/students/enroll', [StudentController::class, 'storeEnrollment'])->name('admin.students.enroll.store');
        Route::get('admin/students', [StudentController::class, 'index'])->name('admin.students.index');
        Route::get('admin/students/{student}', [StudentController::class, 'show'])->name('admin.students.show');

        Route::get('admin/courses', [CourseController::class, 'index'])->name('admin.courses.index');
        Route::get('admin/courses/create', [CourseController::class, 'create'])->name('admin.courses.create');
        Route::post('admin/courses', [CourseController::class, 'store'])->name('admin.courses.store');
        Route::get('admin/courses/{course}', [CourseController::class, 'show'])->name('admin.courses.show');
        Route::get('admin/one-time-sessions', [OneTimeSessionController::class, 'index'])->name('admin.one-time-sessions.index');
        Route::get('admin/one-time-sessions/create', [OneTimeSessionController::class, 'create'])->name('admin.one-time-sessions.create');
        Route::post('admin/one-time-sessions', [OneTimeSessionController::class, 'store'])->name('admin.one-time-sessions.store');
        Route::patch('admin/courses/{course}', [CourseController::class, 'update'])->name('admin.courses.update');
        Route::post('admin/courses/{course}/advance-level', [CourseController::class, 'advanceLevel'])->name('admin.courses.advance-level');
        Route::post('admin/courses/{course}/sessions/{courseSession}/attendance', [CourseController::class, 'storeAttendance'])
            ->name('admin.courses.sessions.attendance.store');
        Route::post('admin/courses/{course}/level-content-toggle', [CourseController::class, 'toggleCourseLevelContent'])
            ->name('admin.courses.level-content-toggle');

        Route::get('admin/pre-registrations/{preRegistration}/enroll', [PreRegistrationEnrollmentController::class, 'create'])
            ->name('admin.pre-registrations.enroll.create');
        Route::post('admin/pre-registrations/{preRegistration}/enroll', [PreRegistrationEnrollmentController::class, 'store'])
            ->name('admin.pre-registrations.enroll.store');

        Route::get('admin/users', [UserRoleController::class, 'index'])->name('admin.users.index');
        Route::patch('admin/users/{user}/role', [UserRoleController::class, 'update'])->name('admin.users.role.update');
        Route::patch('admin/users/{user}/status', [UserRoleController::class, 'updateStatus'])->name('admin.users.status.update');

        Route::middleware('owner')->group(function () {
            Route::post('admin/maintenance/cache-clear', [MaintenanceController::class, 'clearApplicationCache'])
                ->name('admin.maintenance.cache-clear');
            Route::post('admin/maintenance/migrate', [MaintenanceController::class, 'runMigrations'])
                ->name('admin.maintenance.migrate');
        });

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('dance-types', [ApiDanceTypeController::class, 'index'])->name('dance-types.index');
            Route::post('dance-types', [ApiDanceTypeController::class, 'store'])->name('dance-types.store');
            Route::post('dance-types/reorder', [ApiDanceTypeController::class, 'reorder'])->name('dance-types.reorder');
            Route::get('dance-types/{danceType}', [ApiDanceTypeController::class, 'show'])->name('dance-types.show');
            Route::patch('dance-types/{danceType}', [ApiDanceTypeController::class, 'update'])->name('dance-types.update');
            Route::delete('dance-types/{danceType}', [ApiDanceTypeController::class, 'destroy'])->name('dance-types.destroy');
            Route::post('dance-types/{danceType}/levels/reorder', [ApiLevelController::class, 'reorder'])
                ->name('dance-types.levels.reorder');

            Route::get('levels', [ApiLevelController::class, 'index'])->name('levels.index');
            Route::get('levels/{level}', [ApiLevelController::class, 'show'])->name('levels.show');
            Route::post('levels', [ApiLevelController::class, 'store'])->name('levels.store');
            Route::patch('levels/{level}', [ApiLevelController::class, 'update'])->name('levels.update');
            Route::delete('levels/{level}', [ApiLevelController::class, 'destroy'])->name('levels.destroy');
            Route::post('levels/{level}/contents', [LevelContentController::class, 'store'])
                ->name('levels.contents.store');
            Route::post('levels/{level}/contents/reorder', [LevelContentController::class, 'reorder'])
                ->name('levels.contents.reorder');
            Route::patch('level-contents/{levelContent}', [LevelContentController::class, 'update'])
                ->name('level-contents.update');
            Route::delete('level-contents/{levelContent}', [LevelContentController::class, 'destroy'])
                ->name('level-contents.destroy');
        });
    });
});

require __DIR__.'/settings.php';
