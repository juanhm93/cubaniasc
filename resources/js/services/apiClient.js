import axios from 'axios';

const apiClient = axios.create({
    baseURL:
        import.meta.env.VITE_API_URL ??
        (typeof window !== 'undefined' ? window.location.origin : ''),
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

export default apiClient;
