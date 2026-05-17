import type { ReactionType } from './types';

const ICONS: Record<string, string> = {
  love: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
  insightful: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.4 1 1.1 1 1.8V18h6v-1.5c0-.7.4-1.4 1-1.8A7 7 0 0 0 12 2z"/></svg>',
  funny: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M8 14c1 1.5 2.5 2.5 4 2.5S15 15.5 16 14"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
  sad: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M16 16c-1-1.5-2.5-2.5-4-2.5S9 14.5 8 16"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
  surprised: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="15" r="2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
  angry: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M16 16c-1-1.5-2.5-2.5-4-2.5S9 14.5 8 16"/><path d="M7.5 8l2 1.5"/><path d="M16.5 8l-2 1.5"/></svg>',
};

const FALLBACK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>';

const EMOJI_ICONS: Record<string, string> = {
  love: '❤️',
  insightful: '💡',
  funny: '😄',
  sad: '😢',
  surprised: '😮',
  angry: '😠',
};

export function iconFor(type: ReactionType, style: 'classic' | 'emoji' = 'classic'): string {
  if (style === 'emoji') {
    return `<span aria-hidden="true">${EMOJI_ICONS[type] ?? '•'}</span>`;
  }
  return ICONS[type] ?? FALLBACK;
}

export const REACTION_LABELS: Record<string, string> = {
  love: 'Love',
  insightful: 'Insightful',
  funny: 'Funny',
  sad: 'Sad',
  surprised: 'Surprised',
  angry: 'Angry',
};

export function labelFor(type: ReactionType): string {
  return REACTION_LABELS[type] ?? type.charAt(0).toUpperCase() + type.slice(1);
}
