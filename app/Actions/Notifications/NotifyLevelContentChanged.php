<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\LevelContent;
use App\Models\User;
use App\Notifications\LevelContentChangedNotification;
use App\Support\NotificationRecipients;
use App\Support\SafeNotifier;
use Illuminate\Support\Facades\Cache;

/**
 * Catalog figure created, edited or deleted: only the owner is told.
 *
 * The bell records every change, but the email goes out at most once per
 * figure in the cooldown window so loading content does not flood the inbox.
 */
final class NotifyLevelContentChanged
{
    /**
     * @param  list<string>  $changes  LevelContentChangedNotification::* codes.
     */
    public function execute(LevelContent $figure, array $changes, ?User $actor): void
    {
        if ($changes === []) {
            return;
        }

        SafeNotifier::afterResponse(function () use ($figure, $changes, $actor): void {
            $figure->loadMissing('level.danceType');

            $cooldownMinutes = max(1, (int) config('cubania.notifications.content_mail_cooldown_minutes'));

            foreach (NotificationRecipients::combine($actor, NotificationRecipients::owners()) as $owner) {
                $includeMail = Cache::add(
                    'content-mail:'.$owner->id.':'.$figure->id,
                    true,
                    now()->addMinutes($cooldownMinutes),
                );

                SafeNotifier::attempt(fn () => $owner->notify(
                    new LevelContentChangedNotification($figure, $changes, $actor?->name, $includeMail),
                ));
            }
        });
    }

    /**
     * What changed in the last save of the figure, from Eloquent's own record
     * of the update (call right after update()).
     *
     * @return list<string>
     */
    public static function changesFromUpdate(LevelContent $figure): array
    {
        $changed = $figure->getChanges();
        $previous = $figure->getPrevious();
        $changes = [];

        if (array_key_exists('name', $changed)) {
            $changes[] = LevelContentChangedNotification::NAME_CHANGED;
        }

        if (array_key_exists('description', $changed)) {
            $changes[] = self::transition(
                $previous['description'] ?? null,
                $changed['description'],
                LevelContentChangedNotification::DESCRIPTION_ADDED,
                LevelContentChangedNotification::DESCRIPTION_CHANGED,
                LevelContentChangedNotification::DESCRIPTION_REMOVED,
            );
        }

        if (array_key_exists('video_url', $changed)) {
            $changes[] = self::transition(
                $previous['video_url'] ?? null,
                $changed['video_url'],
                LevelContentChangedNotification::VIDEO_ADDED,
                LevelContentChangedNotification::VIDEO_CHANGED,
                LevelContentChangedNotification::VIDEO_REMOVED,
            );
        }

        return $changes;
    }

    private static function transition(mixed $before, mixed $after, string $added, string $changed, string $removed): string
    {
        $hadValue = is_string($before) && trim($before) !== '';
        $hasValue = is_string($after) && trim($after) !== '';

        return match (true) {
            ! $hadValue && $hasValue => $added,
            $hadValue && ! $hasValue => $removed,
            default => $changed,
        };
    }
}
