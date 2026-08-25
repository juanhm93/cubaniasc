import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import type { FormEventHandler } from 'react';
import { useMemo, useState } from 'react';
import { youtubeWatchUrlToEmbedUrl } from '@/components/base/cubania/youtube-embed-url';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/i18n/use-translation';
import admin from '@/routes/admin';

type LocationOption = {
    id: number;
    name: string;
};

type LevelRef = {
    id: number;
    name: string;
    slug: string;
};

type SessionRow = {
    id: number;
    session_date: string;
    starts_at: string | null;
    ends_at: string | null;
};

type EnrollmentRow = {
    enrollment_id: number;
    student_id: number;
    student_name: string;
    student_email: string;
    attendance_by_session: Record<string, string>;
};

type LevelContentRow = {
    id: number;
    name: string;
    sort_order: number;
    video_url: string | null;
};

/**
 * Resolves a stored video URL to an embeddable iframe src, a direct file for
 * <video>, or falls back to opening the link (unknown hosts).
 */
function videoPresentation(url: string):
    | { kind: 'iframe'; src: string }
    | { kind: 'video'; src: string }
    | { kind: 'external'; href: string } {
    const trimmed = url.trim();

    if (!trimmed) {
        return { kind: 'external', href: '#' };
    }

    try {
        const parsed = new URL(trimmed);

        if (
            parsed.hostname === 'youtu.be' ||
            parsed.hostname.includes('youtube.com') ||
            parsed.hostname.includes('youtube-nocookie.com')
        ) {
            return {
                kind: 'iframe',
                src: youtubeWatchUrlToEmbedUrl(trimmed),
            };
        }

        if (parsed.hostname.includes('vimeo.com')) {
            if (parsed.hostname.startsWith('player.')) {
                return { kind: 'iframe', src: trimmed };
            }

            const id = parsed.pathname.match(/\/(\d+)/)?.[1];

            if (id) {
                return {
                    kind: 'iframe',
                    src: `https://player.vimeo.com/video/${id}`,
                };
            }
        }
    } catch {
        // Treat as non-URL string below.
    }

    if (/\.(mp4|webm|ogg)(\?|$)/i.test(trimmed)) {
        return { kind: 'video', src: trimmed };
    }

    return { kind: 'external', href: trimmed };
}

type ScheduleSlotRow = {
    weekday: number;
    weekday_label: string;
    starts_at: string;
    ends_at: string;
};

type CourseShowProps = {
    course: {
        id: number;
        is_active: boolean;
        price: string;
        level: LevelRef | null;
        schedule: unknown;
        place: { id: number; name: string } | null;
        teacher: { id: number; name: string } | null;
        schedule_slots: ScheduleSlotRow[];
        schedule_summary: string;
    };
    levelsPath: { level_id: number; name: string; sort_order: number }[];
    nextLevel: { id: number; name: string } | null;
    sessions: SessionRow[];
    selectedSessionId: number | null;
    enrollments: EnrollmentRow[];
    /** Level content ids marked as covered for this course (whole group). */
    courseProgress: Record<string, boolean>;
    levelContents: LevelContentRow[];
    places: LocationOption[];
    teachers: LocationOption[];
};

const ATT_STATUS_VALUES = [
    'present',
    'absent',
    'late',
    'excused',
] as const;

const WEEKDAY_SHORT_KEYS = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
] as const;

function formatDateEs(isoDate: string): string {
    try {
        return new Date(isoDate + 'T12:00:00').toLocaleDateString('es', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        });
    } catch {
        return isoDate;
    }
}

function attendanceDotClass(status: string | undefined): string {
    if (status === 'present') {
        return 'bg-emerald-500';
    }

    if (status === 'late') {
        return 'bg-amber-500';
    }

    if (status === 'absent') {
        return 'bg-rose-500';
    }

    if (status === 'excused') {
        return 'bg-sky-500';
    }

    return 'bg-muted-foreground/40';
}

function daysInMonth(year: number, month: number): number {
    return new Date(year, month, 0).getDate();
}

function patchCourseMeta(
    course: CourseShowProps['course'],
    overrides: Partial<{
        user_id: number;
        place_id: number;
        is_active: boolean;
    }>,
): void {
    const userId = overrides.user_id ?? course.teacher?.id;
    const placeId = overrides.place_id ?? course.place?.id;

    if (userId == null || placeId == null) {
        return;
    }

    router.patch(
        admin.courses.update.url(course.id),
        {
            is_active: overrides.is_active ?? course.is_active,
            user_id: userId,
            place_id: placeId,
        },
        { preserveScroll: true },
    );
}

export default function AdminCourseShow({
    course,
    levelsPath,
    nextLevel,
    sessions,
    selectedSessionId,
    enrollments,
    courseProgress,
    levelContents,
    places,
    teachers,
}: CourseShowProps) {
    const { t } = useTranslation();
    const { props } = usePage<{ errors?: Record<string, string> }>();
    const formErrors = props.errors ?? {};
    const [modalStudentId, setModalStudentId] = useState<number | null>(null);
    const [previewFigure, setPreviewFigure] = useState<LevelContentRow | null>(
        null,
    );
    const now = new Date();
    const [calYear, setCalYear] = useState(now.getFullYear());
    const [calMonth, setCalMonth] = useState(now.getMonth() + 1);

    function attendanceLabel(status: string | undefined): string {
        if (!status) {
            return t('admin.attendance.unmarked');
        }

        if (
            status === 'present' ||
            status === 'absent' ||
            status === 'late' ||
            status === 'excused'
        ) {
            return t(`admin.attendance.${status}`);
        }

        return status;
    }

    const modalStudent = enrollments.find(
        (e) => e.student_id === modalStudentId,
    );

    const sessionsInMonth = useMemo(() => {
        if (!modalStudent) {
            return [];
        }

        const prefix = `${calYear}-${String(calMonth).padStart(2, '0')}`;

        return sessions.filter((s) => s.session_date.startsWith(prefix));
    }, [sessions, modalStudent, calYear, calMonth]);

    const calendarCells = useMemo(() => {
        const dim = daysInMonth(calYear, calMonth);
        const first = new Date(calYear, calMonth - 1, 1).getDay();
        const offsetMon = (first + 6) % 7;
        const cells: ({ day: number } | null)[] = [];

        for (let i = 0; i < offsetMon; i++) {
            cells.push(null);
        }

        for (let d = 1; d <= dim; d++) {
            cells.push({ day: d });
        }

        while (cells.length % 7 !== 0) {
            cells.push(null);
        }

        return cells;
    }, [calYear, calMonth]);

    function sessionOnDay(day: number): SessionRow | undefined {
        const ds = `${calYear}-${String(calMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

        return sessions.find((s) => s.session_date === ds);
    }

    function statusOnDay(
        student: EnrollmentRow,
        day: number,
    ): string | undefined {
        const sess = sessionOnDay(day);

        if (!sess) {
            return undefined;
        }

        return student.attendance_by_session[String(sess.id)];
    }

    function changeSession(sessionId: number): void {
        router.get(
            admin.courses.show.url(course.id, {
                query: { session_id: sessionId },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    const submitAdvance: FormEventHandler = (e) => {
        e.preventDefault();

        if (
            !confirm(
                t('admin.courses.advanceLevelConfirm', {
                    name: nextLevel?.name ?? '',
                }),
            )
        ) {
            return;
        }

        router.post(
            admin.courses.advanceLevel.url(course.id),
            {},
            { preserveScroll: true },
        );
    };

    const courseLevelName = course.level?.name ?? t('common.course');
    const emDash = t('common.emDash');

    return (
        <>
            <Head
                title={t('admin.courses.headTitleCourse', {
                    name: course.level?.name ?? String(course.id),
                })}
            />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {courseLevelName}{' '}
                            <span className="font-normal text-muted-foreground">
                                · {course.place?.name ?? emDash}
                            </span>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('admin.courses.professorPrice', {
                                teacher: course.teacher?.name ?? emDash,
                                price: course.price,
                            })}
                        </p>
                        {course.schedule_slots.length > 0 ? (
                            <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                {course.schedule_slots.map((row, i) => (
                                    <li
                                        key={`${row.weekday}-${row.starts_at}-${i}`}
                                    >
                                        <span className="font-medium text-foreground">
                                            {row.weekday_label}
                                        </span>
                                        : {row.starts_at}–{row.ends_at}
                                    </li>
                                ))}
                            </ul>
                        ) : course.schedule_summary ? (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {course.schedule_summary}
                            </p>
                        ) : null}
                        {levelsPath.length > 0 ? (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {t('admin.courses.levelPath', {
                                    path: levelsPath
                                        .map((l) => l.name)
                                        .join(' → '),
                                })}
                            </p>
                        ) : null}
                    </div>
                    <div className="flex max-w-full flex-col gap-3 sm:max-w-none">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid min-w-[12rem] gap-1">
                                <Label htmlFor="course-teacher">
                                    {t('common.teacher')}
                                </Label>
                                <select
                                    id="course-teacher"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    value={course.teacher?.id ?? ''}
                                    disabled={teachers.length === 0}
                                    onChange={(e) => {
                                        const v = e.target.value;

                                        if (v) {
                                            patchCourseMeta(course, {
                                                user_id: Number(v),
                                            });
                                        }
                                    }}
                                >
                                    {teachers.length === 0 ? (
                                        <option value="">
                                            {t(
                                                'admin.courses.noUsersInAcademy',
                                            )}
                                        </option>
                                    ) : (
                                        <>
                                            <option value="" disabled>
                                                {t(
                                                    'admin.courses.selectTeacher',
                                                )}
                                            </option>
                                            {teachers.map((teacher) => (
                                                <option
                                                    key={teacher.id}
                                                    value={teacher.id}
                                                >
                                                    {teacher.name}
                                                </option>
                                            ))}
                                        </>
                                    )}
                                </select>
                                <InputError message={formErrors.user_id} />
                            </div>
                            <div className="grid min-w-[12rem] gap-1">
                                <Label htmlFor="course-place">
                                    {t('common.place')}
                                </Label>
                                <select
                                    id="course-place"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    value={course.place?.id ?? ''}
                                    disabled={places.length === 0}
                                    onChange={(e) => {
                                        const v = e.target.value;

                                        if (v) {
                                            patchCourseMeta(course, {
                                                place_id: Number(v),
                                            });
                                        }
                                    }}
                                >
                                    {places.length === 0 ? (
                                        <option value="">
                                            {t('admin.courses.noPlaces')}
                                        </option>
                                    ) : (
                                        <>
                                            <option value="" disabled>
                                                {t(
                                                    'admin.courses.selectPlace',
                                                )}
                                            </option>
                                            {places.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name}
                                                </option>
                                            ))}
                                        </>
                                    )}
                                </select>
                                <InputError message={formErrors.place_id} />
                            </div>
                            <div className="grid min-w-[10rem] gap-1">
                                <Label htmlFor="course-active">
                                    {t('admin.courses.courseStatus')}
                                </Label>
                                <select
                                    id="course-active"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    value={course.is_active ? '1' : '0'}
                                    onChange={(e) => {
                                        patchCourseMeta(course, {
                                            is_active: e.target.value === '1',
                                        });
                                    }}
                                >
                                    <option value="1">
                                        {t('common.active')}
                                    </option>
                                    <option value="0">
                                        {t('common.inactive')}
                                    </option>
                                </select>
                                <InputError message={formErrors.is_active} />
                            </div>
                        </div>
                        <div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={admin.courses.index.url()}>
                                    {t('admin.courses.courseList')}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="mb-3 text-lg font-medium">
                            {t('admin.courses.studentsAndAttendance')}
                        </h2>
                        <div className="mb-4 grid gap-2">
                            <Label htmlFor="session-pick">
                                {t('admin.courses.sessionDate')}
                            </Label>
                            <select
                                id="session-pick"
                                className="h-9 max-w-md rounded-md border border-input bg-background px-3 text-sm"
                                value={selectedSessionId ?? ''}
                                onChange={(e) => {
                                    const v = e.target.value;

                                    if (v) {
                                        changeSession(Number(v));
                                    }
                                }}
                                disabled={sessions.length === 0}
                            >
                                {sessions.length === 0 ? (
                                    <option value="">
                                        {t(
                                            'admin.courses.noSessionsRegistered',
                                        )}
                                    </option>
                                ) : (
                                    sessions.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {formatDateEs(s.session_date)}
                                            {s.starts_at
                                                ? ` · ${s.starts_at}`
                                                : ''}
                                        </option>
                                    ))
                                )}
                            </select>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[520px] text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70">
                                        <th className="py-2 text-left font-medium text-muted-foreground">
                                            {t('admin.students.studentLabel')}
                                        </th>
                                        <th className="py-2 text-left font-medium text-muted-foreground">
                                            {t(
                                                'admin.courses.attendanceThisSession',
                                            )}
                                        </th>
                                        <th className="py-2 text-right font-medium text-muted-foreground">
                                            {t('admin.courses.history')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {enrollments.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="py-6 text-center text-muted-foreground"
                                            >
                                                {t(
                                                    'admin.courses.noEnrolledStudents',
                                                )}
                                            </td>
                                        </tr>
                                    ) : (
                                        enrollments.map((row) => (
                                            <tr
                                                key={row.enrollment_id}
                                                className="border-b border-sidebar-border/70 last:border-0"
                                            >
                                                <td className="py-3 align-top">
                                                    <p className="font-medium">
                                                        {row.student_name}
                                                    </p>
                                                    <p className="max-w-[12rem] truncate text-xs text-muted-foreground">
                                                        {row.student_email}
                                                    </p>
                                                </td>
                                                <td className="py-3 align-top">
                                                    {selectedSessionId ? (
                                                        <select
                                                            className="h-9 w-full max-w-[11rem] rounded-md border border-input bg-background px-2 text-sm"
                                                            value={
                                                                row
                                                                    .attendance_by_session[
                                                                    String(
                                                                        selectedSessionId,
                                                                    )
                                                                ] ?? ''
                                                            }
                                                            onChange={(e) => {
                                                                const status =
                                                                    e.target
                                                                        .value;

                                                                if (!status) {
                                                                    return;
                                                                }

                                                                router.post(
                                                                    admin.courses.sessions.attendance.store.url(
                                                                        {
                                                                            course: course.id,
                                                                            courseSession:
                                                                                selectedSessionId,
                                                                        },
                                                                    ),
                                                                    {
                                                                        student_id:
                                                                            row.student_id,
                                                                        status,
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }}
                                                        >
                                                            <option value="">
                                                                {t(
                                                                    'admin.attendance.mark',
                                                                )}
                                                            </option>
                                                            {ATT_STATUS_VALUES.map(
                                                                (value) => (
                                                                    <option
                                                                        key={
                                                                            value
                                                                        }
                                                                        value={
                                                                            value
                                                                        }
                                                                    >
                                                                        {t(
                                                                            `admin.attendance.${value}`,
                                                                        )}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            {emDash}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 text-right align-top">
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => {
                                                            setModalStudentId(
                                                                row.student_id,
                                                            );
                                                            setCalYear(
                                                                now.getFullYear(),
                                                            );
                                                            setCalMonth(
                                                                now.getMonth() +
                                                                    1,
                                                            );
                                                        }}
                                                    >
                                                        {t(
                                                            'admin.courses.viewAttendance',
                                                        )}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-medium">
                                    {t('admin.courses.figuresTitle', {
                                        level:
                                            course.level?.name ??
                                            t('common.level'),
                                    })}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {t('admin.courses.figuresDescription')}
                                </p>
                            </div>
                            {nextLevel ? (
                                <form onSubmit={submitAdvance}>
                                    <Button type="submit" size="sm">
                                        {t('admin.courses.advanceToLevel', {
                                            name: nextLevel.name,
                                        })}
                                    </Button>
                                </form>
                            ) : (
                                <span className="text-xs text-muted-foreground">
                                    {t('admin.courses.noNextLevel')}
                                </span>
                            )}
                        </div>

                        {levelContents.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('admin.courses.noFiguresInCatalog')}
                            </p>
                        ) : (
                            <ul className="divide-y divide-sidebar-border/70 rounded-lg border border-sidebar-border/70">
                                {levelContents.map((lc) => (
                                    <li
                                        key={lc.id}
                                        className="flex items-center gap-3 px-3 py-3"
                                    >
                                        <Checkbox
                                            checked={
                                                !!courseProgress[String(lc.id)]
                                            }
                                            onCheckedChange={() => {
                                                router.post(
                                                    admin.courses.levelContentToggle.url(
                                                        { course: course.id },
                                                    ),
                                                    {
                                                        level_content_id: lc.id,
                                                    },
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                );
                                            }}
                                            aria-label={t(
                                                'admin.courses.figureViewedAria',
                                                { name: lc.name },
                                            )}
                                        />
                                        <span className="min-w-0 flex-1 text-sm">
                                            {lc.name}
                                        </span>
                                        {lc.video_url ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-9 shrink-0 text-muted-foreground"
                                                aria-label={t(
                                                    'admin.courses.viewFigureAria',
                                                    { name: lc.name },
                                                )}
                                                onClick={() =>
                                                    setPreviewFigure(lc)
                                                }
                                            >
                                                <Eye
                                                    className="size-5"
                                                    aria-hidden
                                                />
                                            </Button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>

            <Dialog
                open={previewFigure !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreviewFigure(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {previewFigure?.name ?? t('common.figure')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('admin.courses.figurePreviewDescription')}
                        </DialogDescription>
                    </DialogHeader>
                    {previewFigure?.video_url ? (
                        (() => {
                            const pres = videoPresentation(
                                previewFigure.video_url,
                            );

                            if (pres.kind === 'iframe') {
                                const iframeSrc =
                                    pres.src.includes('youtube.com/embed') ||
                                    pres.src.includes(
                                        'youtube-nocookie.com/embed',
                                    )
                                        ? `${pres.src}${pres.src.includes('?') ? '&' : '?'}rel=0`
                                        : pres.src;

                                return (
                                    <div className="aspect-video w-full overflow-hidden rounded-md border bg-black">
                                        <iframe
                                            title={previewFigure.name}
                                            src={iframeSrc}
                                            className="size-full"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                            allowFullScreen
                                        />
                                    </div>
                                );
                            }

                            if (pres.kind === 'video') {
                                return (
                                    <video
                                        src={pres.src}
                                        controls
                                        className="w-full rounded-md"
                                    >
                                        {t(
                                            'admin.courses.videoFormatUnsupported',
                                        )}
                                    </video>
                                );
                            }

                            return (
                                <p className="text-sm text-muted-foreground">
                                    <a
                                        href={pres.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="font-medium text-primary underline underline-offset-4"
                                    >
                                        {t('admin.courses.openVideoLink')}
                                    </a>
                                </p>
                            );
                        })()
                    ) : null}
                </DialogContent>
            </Dialog>

            <Dialog
                open={modalStudentId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setModalStudentId(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {t('admin.courses.attendanceModalTitle', {
                                name: modalStudent?.student_name ?? '',
                            })}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <div className="flex flex-wrap gap-2">
                            <select
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={calMonth}
                                onChange={(e) =>
                                    setCalMonth(Number(e.target.value))
                                }
                            >
                                {Array.from(
                                    { length: 12 },
                                    (_, i) => i + 1,
                                ).map((m) => (
                                    <option key={m} value={m}>
                                        {new Date(
                                            2000,
                                            m - 1,
                                            1,
                                        ).toLocaleDateString('es', {
                                            month: 'long',
                                        })}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={calYear}
                                onChange={(e) =>
                                    setCalYear(Number(e.target.value))
                                }
                            >
                                {[
                                    now.getFullYear() - 1,
                                    now.getFullYear(),
                                    now.getFullYear() + 1,
                                ].map((y) => (
                                    <option key={y} value={y}>
                                        {y}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-7 gap-1 text-center text-xs">
                            {WEEKDAY_SHORT_KEYS.map((key) => (
                                <div
                                    key={key}
                                    className="font-medium text-muted-foreground"
                                >
                                    {t(`admin.weekdaysShort.${key}`)}
                                </div>
                            ))}
                            {calendarCells.map((cell, idx) =>
                                cell === null ? (
                                    <div key={`e-${idx}`} />
                                ) : (
                                    <div
                                        key={`d-${cell.day}`}
                                        className="flex min-h-[2.5rem] flex-col items-center justify-start gap-0.5 rounded-md border border-transparent py-1"
                                    >
                                        <span className="text-[11px] text-muted-foreground">
                                            {cell.day}
                                        </span>
                                        {sessionOnDay(cell.day) ? (
                                            <span
                                                title={attendanceLabel(
                                                    modalStudent
                                                        ? statusOnDay(
                                                              modalStudent,
                                                              cell.day,
                                                          )
                                                        : undefined,
                                                )}
                                                className={`inline-block size-2 rounded-full ${attendanceDotClass(
                                                    modalStudent
                                                        ? statusOnDay(
                                                              modalStudent,
                                                              cell.day,
                                                          )
                                                        : undefined,
                                                )}`}
                                            />
                                        ) : null}
                                    </div>
                                ),
                            )}
                        </div>

                        <div>
                            <p className="mb-2 text-xs font-medium text-muted-foreground">
                                {t('admin.courses.sessionsThisMonth')}
                            </p>
                            <ul className="max-h-48 space-y-2 overflow-y-auto text-sm">
                                {sessionsInMonth.length === 0 ? (
                                    <li className="text-muted-foreground">
                                        {t(
                                            'admin.courses.noSessionsThisMonth',
                                        )}
                                    </li>
                                ) : (
                                    sessionsInMonth.map((s) => {
                                        const st =
                                            modalStudent?.attendance_by_session[
                                                String(s.id)
                                            ];

                                        return (
                                            <li
                                                key={s.id}
                                                className="flex justify-between gap-2 border-b border-sidebar-border/40 pb-1"
                                            >
                                                <span>
                                                    {formatDateEs(
                                                        s.session_date,
                                                    )}
                                                </span>
                                                <span
                                                    className={
                                                        st
                                                            ? 'text-foreground'
                                                            : 'text-muted-foreground'
                                                    }
                                                >
                                                    {attendanceLabel(st)}
                                                </span>
                                            </li>
                                        );
                                    })
                                )}
                            </ul>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminCourseShow.layout = {
    breadcrumbs: [
        {
            title: 'navigation.courses',
            href: admin.courses.index.url(),
        },
        {
            title: 'admin.breadcrumbs.course',
            href: '#',
        },
    ],
};
