import type { PulsePressData, ReactionType } from './types';

export function isPositive(type: ReactionType | null, data: PulsePressData): boolean {
  if (type === null) {
    return false;
  }
  return Array.isArray(data.positiveReactions) && data.positiveReactions.includes(type);
}
