import type { CountsResponse, ReactionType, ReactResponse } from './types';

class RestError extends Error {
  constructor(public code: string, message: string, public status: number) {
    super(message);
  }
}

function rootFor(data: { root: string }): string {
  return data.root.endsWith('/') ? data.root : `${data.root}/`;
}

async function parseJson<T>(response: Response): Promise<T> {
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    const code = typeof body === 'object' && body && 'code' in body ? String(body.code) : 'pulsepress_request_failed';
    const message = typeof body === 'object' && body && 'message' in body ? String(body.message) : response.statusText;
    throw new RestError(code, message, response.status);
  }
  return body as T;
}

export async function fetchCounts(data: { root: string; postId: number }): Promise<CountsResponse> {
  const response = await fetch(`${rootFor(data)}counts/${data.postId}`, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  return parseJson<CountsResponse>(response);
}

export async function postReaction(
  data: { root: string; nonce: string; postId: number },
  reactionType: ReactionType
): Promise<ReactResponse> {
  const response = await fetch(`${rootFor(data)}react`, {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-WP-Nonce': data.nonce,
    },
    credentials: 'same-origin',
    body: JSON.stringify({ post_id: data.postId, reaction_type: reactionType }),
  });
  return parseJson<ReactResponse>(response);
}

export { RestError };
