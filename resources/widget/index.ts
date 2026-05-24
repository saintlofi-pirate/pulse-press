import { h, render } from 'preact';
import { ReactionBar } from './components/ReactionBar';
import './widget.css';
import type { MoonfarmerReactionsLeadCaptureData } from './types';

function mountAll(data: MoonfarmerReactionsLeadCaptureData): void {
  const mounts = document.querySelectorAll<HTMLElement>('[data-moonfarmer-reactions-lead-capture-widget]');
  mounts.forEach((mount) => {
    const attrPostId = mount.getAttribute('data-moonfarmer-reactions-lead-capture-post-id');
    const postId = attrPostId ? parseInt(attrPostId, 10) : data.postId;
    if (!Number.isFinite(postId) || postId <= 0) {
      return;
    }
    render(h(ReactionBar, { postId, data }), mount);
  });
}

function boot(): void {
  const data = window.MoonfarmerReactionsLeadCaptureData;
  if (!data) {
    return;
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mountAll(data), { once: true });
  } else {
    mountAll(data);
  }
}

boot();
