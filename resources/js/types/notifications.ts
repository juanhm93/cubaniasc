export type NewPreRegistrationData = {
    pre_registration_id: number;
    name: string;
    email: string;
    phone: string | null;
    country: string | null;
    country_label: string | null;
    submitted_at: string | null;
};

export type AppNotification = {
    id: string;
    type: string;
    data: NewPreRegistrationData;
    read_at: string | null;
    created_at: string | null;
};

export type NotificationListResponse = {
    notifications: AppNotification[];
    unread_count: number;
};

export type NotificationUnreadCountResponse = {
    unread_count: number;
};
