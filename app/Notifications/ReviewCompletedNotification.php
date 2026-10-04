<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LevelContent;
use App\Models\ReviewSession;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Cheering email for the student after finishing the daily review.
 */
class ReviewCompletedNotification extends Notification
{
    public function __construct(
        public ReviewSession $session,
        public int $streak,
    ) {}

    /**
     * Students have no notification bell, only email.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->markdown('mail.review.completed', [
                'studentName' => $notifiable->name ?? null,
                'streak' => $this->streak,
                'milestoneMessage' => $this->milestoneMessage(),
                'levelName' => $this->session->level?->name,
                'figureNames' => $this->session->selectedFigures
                    ->map(fn (LevelContent $figure): string => $figure->name)
                    ->values()
                    ->all(),
                'reviewUrl' => route('review-panel'),
            ]);
    }

    private function subject(): string
    {
        return match (true) {
            $this->streak >= 30 => '🏆 ¡30 días de repaso! Eres imparable',
            $this->streak >= 14 => '🔥 ¡Dos semanas seguidas repasando!',
            $this->streak >= 7 => '🔥 ¡Una semana entera de repaso!',
            $this->streak >= 3 => '💪 ¡'.$this->streak.' días seguidos! Vas muy bien',
            default => '🎉 ¡Terminaste tu repaso! Vas muy bien',
        };
    }

    private function milestoneMessage(): string
    {
        return match (true) {
            $this->streak >= 30 => 'Un mes entero sin soltar el ritmo. Esto ya no es constancia, es estilo de vida. 🏆',
            $this->streak >= 14 => 'Dos semanas seguidas: tu cuerpo ya se mueve solo. Se nota en la pista. 🔥',
            $this->streak >= 7 => 'Siete días sin fallar. Así es como las figuras pasan de la cabeza a los pies. 🔥',
            $this->streak >= 3 => 'Ya llevas '.$this->streak.' días seguidos. La constancia es tu mejor pareja de baile. 💪',
            $this->streak === 2 => 'Dos días seguidos: ¡ya empezaste tu racha! Mañana vamos por el tercero. ✨',
            default => 'Cada repaso cuenta. Mañana te esperamos para empezar tu racha. ✨',
        };
    }
}
