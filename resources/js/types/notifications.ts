export type NewPreRegistrationData = {
    pre_registration_id: number;
    name: string;
    email: string;
    phone: string | null;
    country: string | null;
    country_label: string | null;
    submitted_at: string | null;
};

export type NewEnrollmentData = {
    enrollment_id: number;
    student_id: number;
    student_name: string | null;
    course_id: number;
    course_label: string;
    actor_name: string | null;
};

export type PaymentRecordedData = {
    payment_id: number;
    student_id: number;
    student_name: string | null;
    course_id: number;
    course_label: string;
    amount: string;
    period: string | null;
    period_label: string | null;
    actor_name: string | null;
};

export type PaymentRemindersSentData = {
    period: string;
    period_label: string;
    kind: 'window' | 'last_day';
    count: number;
};

export type CourseContentCoveredData = {
    course_id: number;
    course_label: string;
    level_content_id: number;
    figure_name: string;
    covered_count: number;
    total_count: number;
    actor_name: string | null;
};

export type LevelContentChange =
    | 'figure_created'
    | 'figure_deleted'
    | 'name_changed'
    | 'description_added'
    | 'description_changed'
    | 'description_removed'
    | 'video_added'
    | 'video_changed'
    | 'video_removed';

export type LevelContentChangedData = {
    level_content_id: number;
    figure_name: string;
    level_id: number | null;
    level_name: string | null;
    dance_type_id: number | null;
    dance_type_name: string | null;
    changes: LevelContentChange[];
    actor_name: string | null;
};

export type StudentReviewCompletedData = {
    student_id: number;
    student_name: string;
    review_session_id: number;
    level_name: string | null;
    course_id: number | null;
    streak: number;
};

export type CourseLevelAdvancedData = {
    course_id: number;
    course_label: string;
    previous_level_name: string;
    new_level_name: string;
    student_count: number;
    actor_name: string | null;
};

export type NewPendingUserData = {
    user_id: number;
    user_name: string;
    user_email: string;
};

export type TeacherAssignedData = {
    subject_type: 'course' | 'one_time_session';
    subject_id: number;
    label: string;
    kind_label: string;
    when: string;
    actor_name: string | null;
};

export type NewOneTimeSessionData = {
    one_time_session_id: number;
    name: string;
    type: string;
    type_label: string;
    starts_at_label: string;
    teacher_name: string | null;
    actor_name: string | null;
};

/**
 * Payload of each notification class, keyed by the class basename the API
 * returns as `type`.
 */
export type NotificationDataByType = {
    NewPreRegistrationNotification: NewPreRegistrationData;
    NewEnrollmentNotification: NewEnrollmentData;
    PaymentRecordedNotification: PaymentRecordedData;
    PaymentRemindersSentNotification: PaymentRemindersSentData;
    CourseContentCoveredNotification: CourseContentCoveredData;
    LevelContentChangedNotification: LevelContentChangedData;
    StudentReviewCompletedNotification: StudentReviewCompletedData;
    CourseLevelAdvancedNotification: CourseLevelAdvancedData;
    NewPendingUserNotification: NewPendingUserData;
    TeacherAssignedNotification: TeacherAssignedData;
    NewOneTimeSessionNotification: NewOneTimeSessionData;
};

export type NotificationType = keyof NotificationDataByType;

export type AppNotification = {
    [Type in NotificationType]: {
        id: string;
        type: Type;
        data: NotificationDataByType[Type];
        read_at: string | null;
        created_at: string | null;
    };
}[NotificationType];

export type NotificationListResponse = {
    notifications: AppNotification[];
    unread_count: number;
};

export type NotificationUnreadCountResponse = {
    unread_count: number;
};
