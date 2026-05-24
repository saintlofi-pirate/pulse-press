import type { MoonfarmerReactionsLeadCaptureData, ReactionType } from './types';

export function isPositive(type: ReactionType | null, data: MoonfarmerReactionsLeadCaptureData): boolean {
  if (type === null) {
    return false;
  }
  return Array.isArray(data.positiveReactions) && data.positiveReactions.includes(type);
}
