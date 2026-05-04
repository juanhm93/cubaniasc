import apiClient from '@/services/apiClient';

const LEVELS_API_URL = 'api/levels';

export const getLevels = async () => {
    const response = await apiClient.get(LEVELS_API_URL);

    return response.data;
};

export const getLevel = async (id) => {
    const response = await apiClient.get(`${LEVELS_API_URL}/${id}`);

    return response.data;
};

export const createLevel = async (level) => {
    const response = await apiClient.post(LEVELS_API_URL, level);

    return response.data;
};

export const updateLevelContent = async (id, payload) => {
    const response = await apiClient.patch(
        `api/level-contents/${id}`,
        payload,
    );

    return response.data;
};

export const deleteLevelContent = async (id) => {
    await apiClient.delete(`api/level-contents/${id}`);
};
