export type ReactionType =
  | 'love'
  | 'insightful'
  | 'funny'
  | 'sad'
  | 'surprised'
  | 'angry'
  | string;

export type WidgetDesign = 'minimal' | 'expressive' | 'minimalist' | 'subtle_text' | 'progress_split' | 'vertical_rail' | 'clap_counter';
export type AnimationMode = 'none' | 'subtle' | 'spring' | 'burst' | 'float' | 'glow' | 'count_bump' | 'trail';

export interface CaptureI18n {
  prompt: string;
  label: string;
  placeholder: string;
  consent: string;
  consentHelper: string;
  submit: string;
  submitting: string;
  thanks: string;
  alreadyCaptured: string;
  networkError: string;
  expiredNonce: string;
  dismiss: string;
}

export interface MoonfarmerReactionsLeadCaptureData {
  root: string;
  nonce: string;
  postId: number;
  reactions: ReactionType[];
  positiveReactions: ReactionType[];
  allowGuestReactions?: boolean;
  isLoggedIn?: boolean;
  iconStyle?: 'classic' | 'emoji';
  themeMode?: 'light' | 'dark' | 'auto';
  primaryColor?: string;
  widgetDesign?: WidgetDesign;
  animationMode?: AnimationMode;
  countVisibility?: 'always' | 'never' | 'threshold';
  countThreshold?: number;
  i18n: {
    loading: string;
    error: string;
    activeSuffix: string;
    groupLabel: string;
    announceReacted: string;
    announceUpdated: string;
    capture: CaptureI18n;
  };
}

export interface CountsResponse {
  post_id: number;
  counts: Record<string, number>;
  cached: boolean;
}

export interface ReactResponse {
  post_id: number;
  reaction_type: ReactionType;
  status: 'inserted' | 'updated';
  counts: Record<string, number>;
}

export interface CaptureResponse {
  post_id: number;
  reaction_type: ReactionType;
  email: string;
  status: 'inserted';
  capture_id: number;
}

declare global {
  interface Window {
    MoonfarmerReactionsLeadCaptureData?: MoonfarmerReactionsLeadCaptureData;
  }
}
