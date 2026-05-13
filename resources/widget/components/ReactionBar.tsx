import { useCallback, useEffect, useRef, useState } from 'preact/hooks';
import { fetchCounts, postReaction, RestError } from '../api';
import { iconFor, labelFor } from '../icons';
import { getStoredReaction, setStoredReaction } from '../storage';
import type { PulsePressData, ReactionType } from '../types';

interface Props {
  postId: number;
  data: PulsePressData;
}

const ERROR_VISIBLE_MS = 4000;

export function ReactionBar({ postId, data }: Props) {
  const [counts, setCounts] = useState<Record<string, number>>({});
  const [activeType, setActiveType] = useState<ReactionType | null>(() => getStoredReaction(postId));
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const errorTimerRef = useRef<number | null>(null);

  useEffect(() => {
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
  }, [data.root, postId]);

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
    [activeType, counts, data, pending, postId, showError]
  );

  return (
    <div class="pulsepress-bar" data-loading={pending ? 'true' : 'false'}>
      <div class="pulsepress-buttons" role="group" aria-label="Reactions">
        {data.reactions.map((type) => {
          const isActive = activeType === type;
          return (
            <button
              key={type}
              type="button"
              class="pulsepress-reaction"
              data-active={isActive ? 'true' : 'false'}
              aria-pressed={isActive ? 'true' : 'false'}
              aria-label={`${labelFor(type)}${isActive ? data.i18n.activeSuffix : ''}`}
              onClick={() => handleClick(type)}
            >
              <span class="pulsepress-icon" dangerouslySetInnerHTML={{ __html: iconFor(type) }} />
              <span class="pulsepress-count" aria-live="polite">{counts[type] ?? 0}</span>
            </button>
          );
        })}
      </div>
      {error !== null && (
        <p class="pulsepress-error" role="status">{error}</p>
      )}
    </div>
  );
}
