import review from '@/routes/review';
import type {
    CurrentSessionResult,
    QuizFeedback,
    ReviewAuth,
    ReviewLevel,
    ReviewLevelContent,
    ReviewQuizItem,
    ReviewSession,
    ReviewSong,
    ReviewStreak,
    ReviewStudent,
} from '@/types/review';

const AUTH_STORAGE_KEY = 'cubania_review_auth';

export class ReviewApiError extends Error {
    status: number;
    hasValidationErrors: boolean;
    isDailyLock: boolean;

    constructor(
        status: number,
        message: string,
        options: { hasValidationErrors?: boolean; isDailyLock?: boolean } = {},
    ) {
        super(message);
        this.name = 'ReviewApiError';
        this.status = status;
        this.hasValidationErrors = options.hasValidationErrors ?? false;
        this.isDailyLock = options.isDailyLock ?? false;
    }
}

export function loadReviewAuth(): ReviewAuth | null {
    try {
        const raw = localStorage.getItem(AUTH_STORAGE_KEY);

        return raw ? (JSON.parse(raw) as ReviewAuth) : null;
    } catch {
        clearReviewAuth();

        return null;
    }
}

export function saveReviewAuth(auth: ReviewAuth): void {
    try {
        localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(auth));
    } catch {
        // Private mode or blocked storage: the panel still works until reload.
    }
}

export function clearReviewAuth(): void {
    try {
        localStorage.removeItem(AUTH_STORAGE_KEY);
    } catch {
        // Nothing stored to clear.
    }
}

async function request<T>(
    url: string,
    options: RequestInit = {},
    requireAuth = true,
): Promise<T> {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('Content-Type', 'application/json');

    if (requireAuth) {
        const token = loadReviewAuth()?.token;

        if (!token) {
            throw new ReviewApiError(401, '');
        }

        headers.set('Authorization', `Bearer ${token}`);
    }

    const response = await fetch(url, { ...options, headers });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ReviewApiError(
            response.status,
            typeof payload.message === 'string' ? payload.message : '',
            {
                hasValidationErrors:
                    typeof payload.errors === 'object' &&
                    payload.errors !== null,
                isDailyLock: payload.locked === true,
            },
        );
    }

    return payload as T;
}

type DataResponse<T> = {
    data: T;
};

export async function identifyStudent(payload: {
    email?: string;
    dni?: string;
}): Promise<ReviewAuth> {
    const result = await request<
        DataResponse<{
            token: string;
            student: ReviewStudent;
            level: ReviewLevel;
        }>
    >(
        review.identify.url(),
        { method: 'POST', body: JSON.stringify(payload) },
        false,
    );

    const auth: ReviewAuth = {
        token: result.data.token,
        student: result.data.student,
        level: result.data.level,
    };

    saveReviewAuth(auth);

    return auth;
}

export async function startReviewSession(): Promise<ReviewSession> {
    const result = await request<DataResponse<ReviewSession>>(
        review.sessions.store.url(),
        { method: 'POST' },
    );

    return result.data;
}

export async function fetchCurrentReviewSession(): Promise<CurrentSessionResult> {
    try {
        const result = await request<DataResponse<ReviewSession>>(
            review.sessions.current.url(),
        );

        return { status: 'active', session: result.data };
    } catch (error) {
        if (error instanceof ReviewApiError && error.status === 404) {
            return { status: 'none' };
        }

        if (error instanceof ReviewApiError && error.isDailyLock) {
            return { status: 'locked' };
        }

        throw error;
    }
}

export async function fetchReviewSession(
    sessionId: number,
): Promise<ReviewSession> {
    const result = await request<DataResponse<ReviewSession>>(
        review.sessions.show.url(sessionId),
    );

    return result.data;
}

export async function fetchFigureOptions(
    sessionId: number,
): Promise<ReviewLevelContent[]> {
    const result = await request<DataResponse<ReviewLevelContent[]>>(
        review.sessions.figureOptions.url(sessionId),
    );

    return result.data;
}

export async function storeSelectedFigures(
    sessionId: number,
    levelContentIds: number[],
): Promise<ReviewLevelContent[]> {
    const result = await request<DataResponse<ReviewLevelContent[]>>(
        review.sessions.figures.store.url(sessionId),
        {
            method: 'POST',
            body: JSON.stringify({ level_content_ids: levelContentIds }),
        },
    );

    return result.data;
}

export async function recordFigureView(
    sessionId: number,
    contentId: number,
): Promise<void> {
    await request(
        review.sessions.figures.view.url({
            session: sessionId,
            content: contentId,
        }),
        { method: 'POST' },
    );
}

export async function fetchSessionSongs(
    sessionId: number,
): Promise<ReviewSong[]> {
    const result = await request<DataResponse<ReviewSong[]>>(
        review.sessions.songs.url(sessionId),
    );

    return result.data;
}

export async function fetchNextQuizQuestion(
    sessionId: number,
): Promise<ReviewQuizItem | null> {
    try {
        const result = await request<DataResponse<ReviewQuizItem>>(
            review.sessions.quiz.next.url(sessionId),
        );

        return result.data;
    } catch (error) {
        if (error instanceof ReviewApiError && error.status === 404) {
            return null;
        }

        throw error;
    }
}

export async function submitQuizAnswer(
    sessionId: number,
    itemId: number,
    quizOptionId: number,
): Promise<QuizFeedback> {
    const result = await request<DataResponse<QuizFeedback>>(
        review.sessions.quiz.answer.url({ session: sessionId, item: itemId }),
        {
            method: 'POST',
            body: JSON.stringify({ quiz_option_id: quizOptionId }),
        },
    );

    return result.data;
}

export async function completeReviewSession(sessionId: number): Promise<{
    session: ReviewSession;
    streak: ReviewStreak;
}> {
    const result = await request<
        DataResponse<{ session: ReviewSession; streak: ReviewStreak }>
    >(review.sessions.complete.url(sessionId), { method: 'POST' });

    return result.data;
}

export async function fetchStreak(): Promise<ReviewStreak> {
    const result = await request<DataResponse<ReviewStreak>>(
        review.streak.url(),
    );

    return result.data;
}

export async function logoutReview(): Promise<void> {
    try {
        await request(review.logout.url(), { method: 'DELETE' });
    } finally {
        clearReviewAuth();
    }
}
