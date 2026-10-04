<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LevelContent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Owner alert: a figure of the catalog was created, edited or deleted.
 *
 * The bell always records the change; the email can be skipped
 * ({@see self::$includeMail}) so a content-loading session does not flood the
 * owner's inbox.
 */
class LevelContentChangedNotification extends Notification
{
    public const FIGURE_CREATED = 'figure_created';

    public const FIGURE_DELETED = 'figure_deleted';

    public const NAME_CHANGED = 'name_changed';

    public const DESCRIPTION_ADDED = 'description_added';

    public const DESCRIPTION_CHANGED = 'description_changed';

    public const DESCRIPTION_REMOVED = 'description_removed';

    public const VIDEO_ADDED = 'video_added';

    public const VIDEO_CHANGED = 'video_changed';

    public const VIDEO_REMOVED = 'video_removed';

    /**
     * @var array<string, string>
     */
    private const CHANGE_LABELS = [
        self::FIGURE_CREATED => 'Se creó la figura',
        self::FIGURE_DELETED => 'Se eliminó la figura',
        self::NAME_CHANGED => 'Se cambió el nombre',
        self::DESCRIPTION_ADDED => 'Se agregó la descripción',
        self::DESCRIPTION_CHANGED => 'Se modificó la descripción',
        self::DESCRIPTION_REMOVED => 'Se quitó la descripción',
        self::VIDEO_ADDED => 'Se agregó el video',
        self::VIDEO_CHANGED => 'Se cambió el video',
        self::VIDEO_REMOVED => 'Se quitó el video',
    ];

    /**
     * @param  list<string>  $changes
     */
    public function __construct(
        public LevelContent $figure,
        public array $changes,
        public ?string $actorName = null,
        public bool $includeMail = true,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->includeMail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $level = $this->figure->level;

        return (new MailMessage)
            ->subject($this->subject())
            ->markdown('mail.content.figure-changed', [
                'figure' => $this->figure,
                'levelName' => $level?->name,
                'danceTypeName' => $level?->danceType?->name,
                'changeLabels' => $this->changeLabels(),
                'actorName' => $this->actorName,
                'actionUrl' => $level !== null
                    ? route('content.levels.show', [$level->dance_type_id, $level->id])
                    : route('content.index'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $level = $this->figure->level;

        return [
            'level_content_id' => $this->figure->id,
            'figure_name' => $this->figure->name,
            'level_id' => $level?->id,
            'level_name' => $level?->name,
            'dance_type_id' => $level?->dance_type_id,
            'dance_type_name' => $level?->danceType?->name,
            'changes' => $this->changes,
            'actor_name' => $this->actorName,
        ];
    }

    /**
     * @return list<string>
     */
    public function changeLabels(): array
    {
        return array_values(array_map(
            fn (string $change): string => self::CHANGE_LABELS[$change] ?? $change,
            $this->changes,
        ));
    }

    private function subject(): string
    {
        return match (true) {
            in_array(self::FIGURE_CREATED, $this->changes, true) => '🆕 Nueva figura en el catálogo: '.$this->figure->name,
            in_array(self::FIGURE_DELETED, $this->changes, true) => '🗑️ Figura eliminada del catálogo: '.$this->figure->name,
            default => '✏️ Cambios en la figura '.$this->figure->name,
        };
    }
}
