import type {
  QuizFeedback,
  ReviewAuth,
  ReviewLevelContent,
  ReviewQuizItem,
  ReviewSession,
  ReviewSong,
  ReviewStreak,
  ReviewStudent,
  ReviewLevel,
} from '@/types/review';

const AUTH_STORAGE_KEY = 'cubania_review_auth';

export class ReviewApiError extends Error {
  status: number;

  constructor(status: number, message: string) {
    super(message);
    this.name = 'ReviewApiError';
    this.status = status;
  }
}

export function loadReviewAuth(): ReviewAuth | null {
  const raw = localStorage.getItem(AUTH_STORAGE_KEY);

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as ReviewAuth;
  } catch {
    localStorage.removeItem(AUTH_STORAGE_KEY);

    return null;
  }
}

export function saveReviewAuth(auth: ReviewAuth): void {
  localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(auth));
}

export function clearReviewAuth(): void {
  localStorage.removeItem(AUTH_STORAGE_KEY);
}

function getToken(): string | null {
  return loadReviewAuth()?.token ?? null;
}

async function request<T>(
  path: string,
  options: RequestInit = {},
  requireAuth = true,
): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');

  if (!(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }

  if (requireAuth) {
    const token = getToken();

    if (!token) {
      throw new ReviewApiError(401, 'No review session token found.');
    }

    headers.set('Authorization', `Bearer ${token}`);
  }

  const response = await fetch(`/api/review${path}`, {
    ...options,
    headers,
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new ReviewApiError(
      response.status,
      typeof payload.message === 'string' ? payload.message : 'Request failed.',
    );
  }

  return payload as T;
}

type IdentifyResponse = {
  data: {
    token: string;
    student: ReviewStudent;
    level: ReviewLevel;
  };
};

type SessionResponse = {
  data: ReviewSession;
};

type QuizNextResponse = {
  data: ReviewQuizItem;
};

type QuizAnswerResponse = {
  data: QuizFeedback;
};

type CompleteResponse = {
  data: {
    session: ReviewSession;
    streak: ReviewStreak;
  };
};

type StreakResponse = {
  data: ReviewStreak;
};

type CollectionResponse<T> = {
  data: T[];
};

export async function identifyStudent(payload: {
  email?: string;
  dni?: string;
}): Promise<ReviewAuth> {
  const result = await request<IdentifyResponse>(
    '/identify',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
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

export async function createReviewSession(): Promise<ReviewSession> {
  const result = await request<SessionResponse>('/sessions', { method: 'POST' });

  return result.data;
}

export async function fetchReviewSession(sessionId: number): Promise<ReviewSession> {
  const result = await request<SessionResponse>(`/sessions/${sessionId}`);

  return result.data;
}

export async function fetchFigureOptions(
  sessionId: number,
): Promise<ReviewLevelContent[]> {
  const result = await request<CollectionResponse<ReviewLevelContent>>(
    `/sessions/${sessionId}/figure-options`,
  );

  return result.data;
}

export async function storeSelectedFigures(
  sessionId: number,
  levelContentIds: number[],
): Promise<void> {
  await request(`/sessions/${sessionId}/figures`, {
    method: 'POST',
    body: JSON.stringify({ level_content_ids: levelContentIds }),
  });
}

export async function recordFigureView(
  sessionId: number,
  contentId: number,
): Promise<void> {
  await request(`/sessions/${sessionId}/figures/${contentId}/view`, {
    method: 'POST',
  });
}

export async function fetchSessionSongs(sessionId: number): Promise<ReviewSong[]> {
  const result = await request<CollectionResponse<ReviewSong>>(
    `/sessions/${sessionId}/songs`,
  );

  return result.data;
}

export async function fetchNextQuizQuestion(
  sessionId: number,
): Promise<ReviewQuizItem | null> {
  try {
    const result = await request<QuizNextResponse>(`/sessions/${sessionId}/quiz/next`);

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
  const result = await request<QuizAnswerResponse>(
    `/sessions/${sessionId}/quiz/${itemId}/answer`,
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
  const result = await request<CompleteResponse>(`/sessions/${sessionId}/complete`, {
    method: 'POST',
  });

  return result.data;
}

export async function fetchStreak(): Promise<ReviewStreak> {
  const result = await request<StreakResponse>('/streak');

  return result.data;
}

export async function logoutReview(): Promise<void> {
  try {
    await request('/logout', { method: 'DELETE' });
  } finally {
    clearReviewAuth();
  }
}
