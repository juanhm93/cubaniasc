<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\Course;
use App\Models\CourseLevelContentProgress;
use App\Models\LevelContent;
use App\Models\User;
use App\Notifications\CourseContentCoveredNotification;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;

/**
 * A figure was marked as covered in a course: owner and admins follow the
 * teaching progress.
 */
final class NotifyCourseContentCovered
{
    public function execute(Course $course, LevelContent $figure, ?User $actor): void
    {
        SafeNotifier::afterResponse(function () use ($course, $figure, $actor): void {
            $course->loadMissing(['level.danceType', 'scheduleSlots']);

            $coveredCount = CourseLevelContentProgress::query()
                ->where('course_id', $course->id)
                ->whereIn('level_content_id', LevelContent::query()->where('level_id', $course->level_id)->select('id'))
                ->count();
            $totalCount = LevelContent::query()->where('level_id', $course->level_id)->count();

            SafeNotifier::notifyEach(
                NotificationRecipients::combine($actor, NotificationRecipients::ownersAndAdmins()),
                new CourseContentCoveredNotification($course, $figure, $coveredCount, $totalCount, $actor?->name),
            );
        });
    }
}
