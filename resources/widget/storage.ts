import type { ReactionType } from './types';

const PREFIX = 'pulsepress:reaction:';

function keyFor(postId: number): string {
  return `${PREFIX}${postId}`;
}

export function getStoredReaction(postId: number): ReactionType | null {
  try {
    const value = window.localStorage.getItem(keyFor(postId));
    return value && value.length > 0 ? value : null;
  } catch {
    return null;
  }
}

export function setStoredReaction(postId: number, type: ReactionType | null): void {
  try {
    if (type === null) {
      window.localStorage.removeItem(keyFor(postId));
    } else {
      window.localStorage.setItem(keyFor(postId), type);
    }
  } catch {
    // storage may be disabled; widget operates statelessly in that case.
  }
}
