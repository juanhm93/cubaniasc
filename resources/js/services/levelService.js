import apiClient from '@/services/apiClient';

const LEVELS_API_URL = 'api/levels';
const DANCE_TYPES_API_URL = 'api/dance-types';

export const getDanceTypes = async () => {
    const response = await apiClient.get(DANCE_TYPES_API_URL);

    return response.data;
};

export const createDanceType = async (payload) => {
    const response = await apiClient.post(DANCE_TYPES_API_URL, payload);

    return response.data;
};

export const updateDanceType = async (id, payload) => {
    const response = await apiClient.patch(
        `${DANCE_TYPES_API_URL}/${id}`,
        payload,
    );

    return response.data;
};

export const deleteDanceType = async (id) => {
    await apiClient.delete(`${DANCE_TYPES_API_URL}/${id}`);
};

export const getDanceType = async (id) => {
    const response = await apiClient.get(`${DANCE_TYPES_API_URL}/${id}`);

    return response.data;
};

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

export const deleteLevel = async (id) => {
    await apiClient.delete(`${LEVELS_API_URL}/${id}`);
};

export const createLevelContent = async (levelId, payload) => {
    const response = await apiClient.post(
        `${LEVELS_API_URL}/${levelId}/contents`,
        payload,
    );

    return response.data;
};

export const updateLevelContent = async (id, payload) => {
    const response = await apiClient.patch(`api/level-contents/${id}`, payload);

    return response.data;
};

export const deleteLevelContent = async (id) => {
    await apiClient.delete(`api/level-contents/${id}`);
};
