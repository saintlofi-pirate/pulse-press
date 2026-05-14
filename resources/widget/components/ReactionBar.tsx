import { useCallback, useEffect, useMemo, useRef, useState } from 'preact/hooks';
import { fetchCounts, postReaction, RestError } from '../api';
import { iconFor, labelFor } from '../icons';
import { isPositive } from '../positive';
import {
  getCapturedFlag,
  getStoredReaction,
  setCapturedFlag,
  setStoredReaction,
} from '../storage';
import type { PulsePressData, ReactionType } from '../types';
import { CaptureForm } from './CaptureForm';

interface Props {
  postId: number;
  data: PulsePressData;
  initialCounts?: Record<string, number>;
  previewOnly?: boolean;
}

const ERROR_VISIBLE_MS = 4000;

export function ReactionBar({ postId, data, initialCounts, previewOnly = false }: Props) {
  const [counts, setCounts] = useState<Record<string, number>>(() => initialCounts ?? {});
  const [activeType, setActiveType] = useState<ReactionType | null>(() => previewOnly ? null : getStoredReaction(postId));
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [announcement, setAnnouncement] = useState<string>('');
  const [captured, setCaptured] = useState<boolean>(() => previewOnly ? false : getCapturedFlag(postId));
  const [dismissed, setDismissed] = useState<boolean>(false);
  const errorTimerRef = useRef<number | null>(null);
  const buttonRefs = useMemo(() => new Map<ReactionType, { current: HTMLButtonElement | null }>(), []);

  const announce = useCallback((message: string) => {
    setAnnouncement('');
    window.setTimeout(() => setAnnouncement(message), 50);
  }, []);

  useEffect(() => {
    if (previewOnly) return;
    let cancelled = false;
    fetchCounts({ root: data.root, postId })
      .then((response) => {
        if (!cancelled) {
          setCounts(response.counts ?? {});
        }
      })
      .catch(() => {
        // First-paint counts failure is non-fatal; the bar still renders with zeros.
      });
    return () => {
      cancelled = true;
    };
  }, [data.root, postId, previewOnly]);

  useEffect(() => {
    return () => {
      if (errorTimerRef.current !== null) {
        window.clearTimeout(errorTimerRef.current);
      }
    };
  }, []);

  const showError = useCallback((message: string) => {
    setError(message);
    if (errorTimerRef.current !== null) {
      window.clearTimeout(errorTimerRef.current);
    }
    errorTimerRef.current = window.setTimeout(() => {
      setError(null);
      errorTimerRef.current = null;
    }, ERROR_VISIBLE_MS);
  }, []);

  const handleClick = useCallback(
    async (type: ReactionType) => {
      if (pending || type === activeType) {
        return;
      }

      const prevActive = activeType;
      const prevCounts = counts;

      const optimistic = { ...prevCounts };
      if (prevActive && optimistic[prevActive]) {
        optimistic[prevActive] = Math.max(0, optimistic[prevActive] - 1);
      }
      optimistic[type] = (optimistic[type] ?? 0) + 1;

      setActiveType(type);
      setCounts(optimistic);
      setStoredReaction(postId, type);
      setError(null);
      setPending(true);

      try {
        const response = await postReaction({ root: data.root, nonce: data.nonce, postId }, type);
        setCounts(response.counts ?? optimistic);
        const template = response.status === 'inserted' ? data.i18n.announceReacted : data.i18n.announceUpdated;
        announce(template.replace('{type}', labelFor(type)));
      } catch (err) {
        setActiveType(prevActive);
        setCounts(prevCounts);
        setStoredReaction(postId, prevActive);
        const message = err instanceof RestError ? err.message : data.i18n.error;
        showError(message || data.i18n.error);
      } finally {
        setPending(false);
      }
    },
    [activeType, announce, counts, data, pending, postId, showError]
  );

  const handleCaptureDone = useCallback(
    (result: { status: 'inserted' | 'already_exists' }) => {
      setCaptured(true);
      setCapturedFlag(postId);
      if (result.status === 'inserted') {
        announce(data.i18n.capture.thanks);
      }
    },
    [announce, data.i18n.capture.thanks, postId]
  );

  const handleCaptureDismiss = useCallback(() => {
    setDismissed(true);
  }, []);

  const ensureRef = (type: ReactionType): { current: HTMLButtonElement | null } => {
    if (!buttonRefs.has(type)) {
      buttonRefs.set(type, { current: null });
    }
    return buttonRefs.get(type)!;
  };

  const showCapture =
    !captured &&
    !dismissed &&
    activeType !== null &&
    isPositive(activeType, data) &&
    !pending;

  return (
    <div class="pulsepress-bar" data-loading={pending ? 'true' : 'false'}>
      <div class="pulsepress-buttons" role="group" aria-label={data.i18n.groupLabel}>
        {data.reactions.map((type) => {
          const isActive = activeType === type;
          const count = counts[type] ?? 0;
          const ref = ensureRef(type);
          return (
            <button
              key={type}
              ref={(node: HTMLButtonElement | null) => { ref.current = node; }}
              type="button"
              class="pulsepress-reaction"
              data-active={isActive ? 'true' : 'false'}
              aria-pressed={isActive ? 'true' : 'false'}
              aria-label={`${labelFor(type)}, ${count}${isActive ? data.i18n.activeSuffix : ''}`}
              onClick={() => handleClick(type)}
            >
              <span class="pulsepress-icon" dangerouslySetInnerHTML={{ __html: iconFor(type) }} />
              <span class="pulsepress-count" aria-hidden="true">{count}</span>
            </button>
          );
        })}
      </div>
      <p class="pulsepress-sr-only" role="status" aria-live="polite" aria-atomic="true">{announcement}</p>
      {error !== null && (
        <p class="pulsepress-error" role="alert">{error}</p>
      )}
      {showCapture && activeType !== null && (
        <CaptureForm
          postId={postId}
          reactionType={activeType}
          data={data}
          triggerRef={ensureRef(activeType)}
          onDone={handleCaptureDone}
          onDismiss={handleCaptureDismiss}
        />
      )}
    </div>
  );
}
