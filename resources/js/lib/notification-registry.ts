import type { TFunction } from 'i18next';
import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    Bell,
    CalendarClock,
    CalendarPlus,
    CircleCheck,
    Flame,
    GraduationCap,
    PencilLine,
    Presentation,
    Rocket,
    UserCheck,
    UserPlus,
} from 'lucide-react';
import admin from '@/routes/admin';
import content from '@/routes/content';
import type { AuthAbilities } from '@/types';
import type {
    AppNotification,
    LevelContentChange,
    LevelContentChangedData,
} from '@/types/notifications';

export type NotificationPresentation = {
    icon: LucideIcon;
    title: string;
    subtitle: string | null;
    /** Null when the viewer cannot open the related screen. */
    href: string | null;
};

function joinParts(...parts: Array<string | null | undefined>): string | null {
    const filled = parts.filter(
        (part): part is string => typeof part === 'string' && part !== '',
    );

    return filled.length > 0 ? filled.join(' · ') : null;
}

function byActor(t: TFunction, actorName: string | null): string | null {
    return actorName
        ? t('notifications.types.byActor', { name: actorName })
        : null;
}

function changeLabel(t: TFunction, change: LevelContentChange): string {
    switch (change) {
        case 'figure_created':
            return t('notifications.types.changes.figureCreated');
        case 'figure_deleted':
            return t('notifications.types.changes.figureDeleted');
        case 'name_changed':
            return t('notifications.types.changes.nameChanged');
        case 'description_added':
            return t('notifications.types.changes.descriptionAdded');
        case 'description_changed':
            return t('notifications.types.changes.descriptionChanged');
        case 'description_removed':
            return t('notifications.types.changes.descriptionRemoved');
        case 'video_added':
            return t('notifications.types.changes.videoAdded');
        case 'video_changed':
            return t('notifications.types.changes.videoChanged');
        case 'video_removed':
            return t('notifications.types.changes.videoRemoved');
    }
}

function levelContentTitle(
    t: TFunction,
    data: LevelContentChangedData,
): string {
    if (data.changes.includes('figure_created')) {
        return t('notifications.types.levelContentCreated', {
            figure: data.figure_name,
        });
    }

    if (data.changes.includes('figure_deleted')) {
        return t('notifications.types.levelContentDeleted', {
            figure: data.figure_name,
        });
    }

    return t('notifications.types.levelContentChanged', {
        figure: data.figure_name,
    });
}

/**
 * How each notification type looks in the bell and where it leads. The link
 * is built here from ids because the stored payload never contains URLs.
 */
export function presentNotification(
    notification: AppNotification,
    t: TFunction,
    abilities: AuthAbilities,
): NotificationPresentation {
    switch (notification.type) {
        case 'NewPreRegistrationNotification': {
            const { data } = notification;

            return {
                icon: UserPlus,
                title: t('notifications.newPreRegistration', {
                    name: data.name,
                }),
                subtitle: joinParts(data.email, data.country_label),
                href: abilities.payments
                    ? admin.payments.index.url({
                          query: {
                              tab: 'mas',
                              preRegistration: data.pre_registration_id,
                          },
                      })
                    : null,
            };
        }

        case 'NewEnrollmentNotification': {
            const { data } = notification;
            let href: string | null = null;

            if (abilities.students) {
                href = admin.students.show.url(data.student_id);
            } else if (abilities.courses) {
                href = admin.courses.show.url(data.course_id);
            }

            return {
                icon: GraduationCap,
                title: t('notifications.types.newEnrollment', {
                    student: data.student_name ?? '',
                    course: data.course_label,
                }),
                subtitle: byActor(t, data.actor_name),
                href,
            };
        }

        case 'PaymentRecordedNotification': {
            const { data } = notification;

            return {
                icon: Banknote,
                title: t('notifications.types.paymentRecorded', {
                    student: data.student_name ?? '',
                    amount: data.amount,
                }),
                subtitle: joinParts(
                    data.course_label,
                    data.period_label,
                    byActor(t, data.actor_name),
                ),
                href:
                    abilities.payments && data.period
                        ? admin.payments.index.url({
                              query: { month: data.period },
                          })
                        : null,
            };
        }

        case 'PaymentRemindersSentNotification': {
            const { data } = notification;

            return {
                icon: CalendarClock,
                title:
                    data.kind === 'last_day'
                        ? t('notifications.types.paymentRemindersSentLastDay', {
                              count: data.count,
                              period: data.period_label,
                          })
                        : t('notifications.types.paymentRemindersSentWindow', {
                              count: data.count,
                              period: data.period_label,
                          }),
                subtitle: null,
                href: abilities.payments
                    ? admin.payments.index.url({
                          query: { month: data.period },
                      })
                    : null,
            };
        }

        case 'CourseContentCoveredNotification': {
            const { data } = notification;

            return {
                icon: CircleCheck,
                title: t('notifications.types.contentCovered', {
                    figure: data.figure_name,
                }),
                subtitle: joinParts(
                    data.course_label,
                    t('notifications.types.contentCoveredProgress', {
                        covered: data.covered_count,
                        total: data.total_count,
                    }),
                    byActor(t, data.actor_name),
                ),
                href: abilities.courses
                    ? admin.courses.show.url(data.course_id)
                    : null,
            };
        }

        case 'LevelContentChangedNotification': {
            const { data } = notification;

            return {
                icon: PencilLine,
                title: levelContentTitle(t, data),
                subtitle: joinParts(
                    data.changes
                        .filter(
                            (change) =>
                                change !== 'figure_created' &&
                                change !== 'figure_deleted',
                        )
                        .map((change) => changeLabel(t, change))
                        .join(', '),
                    data.level_name,
                    byActor(t, data.actor_name),
                ),
                href:
                    abilities.content &&
                    data.dance_type_id !== null &&
                    data.level_id !== null
                        ? content.levels.show.url({
                              danceType: data.dance_type_id,
                              level: data.level_id,
                          })
                        : null,
            };
        }

        case 'StudentReviewCompletedNotification': {
            const { data } = notification;
            let href: string | null = null;

            if (abilities.students) {
                href = admin.students.show.url(data.student_id);
            } else if (abilities.courses && data.course_id !== null) {
                href = admin.courses.show.url(data.course_id);
            }

            return {
                icon: Flame,
                title: t('notifications.types.reviewCompleted', {
                    student: data.student_name,
                }),
                subtitle: joinParts(
                    t('notifications.types.reviewStreak', {
                        count: data.streak,
                    }),
                    data.level_name,
                ),
                href,
            };
        }

        case 'CourseLevelAdvancedNotification': {
            const { data } = notification;

            return {
                icon: Rocket,
                title: t('notifications.types.levelAdvanced', {
                    course: data.course_label,
                }),
                subtitle: joinParts(
                    t('notifications.types.levelAdvancedPath', {
                        from: data.previous_level_name,
                        to: data.new_level_name,
                    }),
                    byActor(t, data.actor_name),
                ),
                href: abilities.courses
                    ? admin.courses.show.url(data.course_id)
                    : null,
            };
        }

        case 'NewPendingUserNotification': {
            const { data } = notification;

            return {
                icon: UserCheck,
                title: t('notifications.types.pendingUser', {
                    name: data.user_name,
                }),
                subtitle: data.user_email,
                href: abilities.adminUsers ? admin.users.index.url() : null,
            };
        }

        case 'TeacherAssignedNotification': {
            const { data } = notification;
            let href: string | null = null;

            if (data.subject_type === 'course' && abilities.courses) {
                href = admin.courses.show.url(data.subject_id);
            } else if (
                data.subject_type === 'one_time_session' &&
                abilities.oneTimeSessions
            ) {
                href = admin.oneTimeSessions.index.url();
            }

            return {
                icon: Presentation,
                title: t('notifications.types.teacherAssigned', {
                    label: data.label,
                }),
                subtitle: joinParts(
                    data.kind_label,
                    data.when,
                    byActor(t, data.actor_name),
                ),
                href,
            };
        }

        case 'NewOneTimeSessionNotification': {
            const { data } = notification;

            return {
                icon: CalendarPlus,
                title: t('notifications.types.newOneTimeSession', {
                    name: data.name,
                }),
                subtitle: joinParts(
                    data.type_label,
                    data.starts_at_label,
                    data.teacher_name,
                ),
                href: abilities.oneTimeSessions
                    ? admin.oneTimeSessions.index.url()
                    : null,
            };
        }

        default:
            return {
                icon: Bell,
                title: t('notifications.types.generic'),
                subtitle: null,
                href: null,
            };
    }
}
