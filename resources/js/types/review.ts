export type ReviewStudent = {
    id: number;
    name: string;
    email: string;
    dni: string | null;
};

export type ReviewLevel = {
    id: number;
    name: string;
    slug: string;
    review_duration_seconds: number;
};

export type ReviewAuth = {
    token: string;
    student: ReviewStudent;
    level: ReviewLevel;
};

export type ReviewLevelContent = {
    id: number;
    level_id: number;
    level_name?: string | null;
    name: string;
    description: string | null;
    video_url: string | null;
    sort_order: number;
};

export type ReviewSession = {
    id: number;
    level_id: number;
    started_at: string;
    expires_at: string;
    completed: boolean;
    completed_at: string | null;
    duration_seconds: number;
    remaining_seconds: number;
    expired: boolean;
    quiz_max: number;
    quiz_answered_count?: number;
    selected_figures?: ReviewLevelContent[];
    level?: ReviewLevel;
};

export type ReviewSong = {
    id: number;
    title: string;
    artist: string;
    audio_or_link_url: string;
};

export type ReviewQuizOption = {
    id: number;
    description: string;
};

export type ReviewQuizItem = {
    id: number;
    type: 'figure' | 'fun_fact';
    prompt: string;
    options: ReviewQuizOption[];
};

export type ReviewStreakDay = {
    date: string;
    completed: boolean;
};

export type ReviewStreak = {
    current_streak: number;
    last_review_at: string | null;
    recent_days: ReviewStreakDay[];
    next_day_starts_at: string;
};

export type ReviewStep =
    | 'identify'
    | 'dashboard'
    | 'figures-select'
    | 'figures-review'
    | 'quiz'
    | 'songs'
    | 'complete'
    | 'locked'
    | 'expired';

export type QuizFeedback = {
    is_correct: boolean;
    correct_option_id: number | null;
    answered_at: string;
};

export type CurrentSessionResult =
    | { status: 'active'; session: ReviewSession }
    | { status: 'none' }
    | { status: 'locked' };
