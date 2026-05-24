import type { ReactionType } from './types';

const REACTION_PREFIX = 'moonfarmer-reactions-lead-capture:reaction:';
const CAPTURED_PREFIX = 'moonfarmer-reactions-lead-capture:captured:';

function reactionKey(postId: number): string {
  return `${REACTION_PREFIX}${postId}`;
}

function capturedKey(postId: number): string {
  return `${CAPTURED_PREFIX}${postId}`;
}

export function getStoredReaction(postId: number): ReactionType | null {
  try {
    const value = window.localStorage.getItem(reactionKey(postId));
    return value && value.length > 0 ? value : null;
  } catch {
    return null;
  }
}

export function setStoredReaction(postId: number, type: ReactionType | null): void {
  try {
    if (type === null) {
      window.localStorage.removeItem(reactionKey(postId));
    } else {
      window.localStorage.setItem(reactionKey(postId), type);
    }
  } catch {
    // storage may be disabled; widget operates statelessly in that case.
  }
}

export function getCapturedFlag(postId: number): boolean {
  try {
    return window.localStorage.getItem(capturedKey(postId)) === '1';
  } catch {
    return false;
  }
}

export function setCapturedFlag(postId: number): void {
  try {
    window.localStorage.setItem(capturedKey(postId), '1');
  } catch {
    // local-only convenience; server-side dedup is authoritative.
  }
}
