import apiClient from '@/services/apiClient';
import type {
    NotificationListResponse,
    NotificationUnreadCountResponse,
} from '@/types/notifications';

const NOTIFICATIONS_API_URL = 'api/notifications';

export const getNotifications = async (): Promise<NotificationListResponse> => {
    const response = await apiClient.get(NOTIFICATIONS_API_URL);

    return response.data;
};

export const markNotificationAsRead = async (
    id: string,
): Promise<NotificationUnreadCountResponse> => {
    const response = await apiClient.patch(
        `${NOTIFICATIONS_API_URL}/${id}/read`,
    );

    return response.data;
};

export const markAllNotificationsAsRead =
    async (): Promise<NotificationUnreadCountResponse> => {
        const response = await apiClient.post(
            `${NOTIFICATIONS_API_URL}/read-all`,
        );

        return response.data;
    };
