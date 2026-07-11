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

export type ReviewSession = {
  id: number;
  level_id: number;
  started_at: string;
  expires_at: string;
  completed: boolean;
  remaining_seconds: number;
  expired: boolean;
  level?: ReviewLevel;
};

export type ReviewLevelContent = {
  id: number;
  name: string;
  description: string | null;
  video_url: string | null;
  sort_order: number;
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

export type ReviewStreak = {
  current_streak: number;
  last_review_at: string | null;
};

export type ReviewStep =
  | 'identify'
  | 'figures-select'
  | 'figures-review'
  | 'quiz'
  | 'songs'
  | 'complete';

export type QuizFeedback = {
  is_correct: boolean;
  answered_at: string;
};
