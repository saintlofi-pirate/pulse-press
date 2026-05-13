export type ReactionType =
  | 'love'
  | 'insightful'
  | 'funny'
  | 'sad'
  | 'surprised'
  | 'angry'
  | string;

export interface PulsePressData {
  root: string;
  nonce: string;
  postId: number;
  reactions: ReactionType[];
  i18n: {
    loading: string;
    error: string;
    activeSuffix: string;
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

declare global {
  interface Window {
    PulsePressData?: PulsePressData;
  }
}
