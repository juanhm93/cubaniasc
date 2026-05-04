<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('rif', 120)->nullable();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('logo', 512)->nullable()->comment('Path relative to the public disk (e.g. companies/logos/xyz.png)');
            $table->string('website', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('is_owner')->default(0)->after('remember_token');
            $table->string('status', 32)->default('pending')->after('is_owner');
            $table->string('phone', 32)->nullable()->after('status');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete()->after('phone');
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete()->after('company_id');
            $table->softDeletes();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->json('days');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('sort_order')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('sort_order')->unique();
            $table->foreignId('dance_type_id')->constrained()->restrictOnDelete();
            $table->index('dance_type_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('level_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('video_url', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['level_id', 'sort_order']);
        });

        Schema::create('pre_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable();
            $table->boolean('agree')->default(false);
            $table->text('message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('dni', 32)->nullable()->index();
            $table->string('email')->unique();
            $table->date('birthday')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('zip', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('price', 10, 2);
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('place_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('level_id');
        });

        Schema::create('course_schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('1=Lun .. 7=Dom');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'weekday']);
        });

        Schema::create('course_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->unique(['course_id', 'level_id']);
            $table->unique(['course_id', 'sort_order']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['course_id', 'student_id']);
        });

        Schema::create('course_level_content_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_content_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'level_content_id'], 'clcp_course_lc_unique');
        });

        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->date('session_date')->index();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'session_date']);
        });

        Schema::create('one_time_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('place_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->index()->comment('workshop, private_class, event');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'starts_at']);
            $table->index(['company_id', 'starts_at']);
        });

        Schema::create('one_time_session_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('one_time_session_id')->constrained('one_time_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('phone', 32)->nullable();
            $table->string('school', 160)->nullable();
            $table->string('payment_status', 32)->default('pending')->index();
            $table->decimal('amount_due', 10, 2)->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['one_time_session_id', 'student_id'], 'otsa_session_student_unique');
            $table->index(['one_time_session_id', 'payment_status'], 'otsa_session_payment_idx');
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('present')->index();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['course_session_id', 'student_id']);
        });

        Schema::create('session_level_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_content_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['course_session_id', 'level_content_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->string('reference', 255)->nullable();
            $table->string('receipt_path', 512)->nullable()->comment('Proof file path on the public disk');
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('method', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('session_level_contents');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('one_time_session_attendees');
        Schema::dropIfExists('one_time_sessions');
        Schema::dropIfExists('course_sessions');
        Schema::dropIfExists('course_level_content_progress');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('course_levels');
        Schema::dropIfExists('course_schedule_slots');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('pre_registrations');
        Schema::dropIfExists('level_contents');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('dance_types');
        Schema::dropIfExists('places');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
            $table->dropColumn('status');
            $table->dropColumn('phone');
            $table->dropSoftDeletes();
        });
        Schema::dropIfExists('roles');
        Schema::dropIfExists('companies');
    }
};
