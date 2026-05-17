import { useCallback, useEffect, useMemo, useRef, useState } from 'preact/hooks';
import type { ComponentType } from 'preact';
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
import type { CaptureForm as CaptureFormComponent } from './CaptureForm';

type CaptureFormProps = Parameters<typeof CaptureFormComponent>[0];

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
  const [captureCompleted, setCaptureCompleted] = useState<boolean>(false);
  const [dismissed, setDismissed] = useState<boolean>(false);
  const [LazyCaptureForm, setLazyCaptureForm] = useState<ComponentType<CaptureFormProps> | null>(null);
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

  const reactionsDisabled = data.allowGuestReactions === false && data.isLoggedIn !== true;

  const handleClick = useCallback(
    async (type: ReactionType) => {
      if (pending || reactionsDisabled || type === activeType) {
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
      setError(null);

      if (previewOnly) {
        announce(data.i18n.announceReacted.replace('{type}', labelFor(type)));
        return;
      }

      setStoredReaction(postId, type);
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
    [activeType, announce, counts, data, pending, postId, reactionsDisabled, showError]
  );

  const handleCaptureDone = useCallback(
    (result: { status: 'inserted' | 'already_exists' }) => {
      setCaptureCompleted(true);
      setCapturedFlag(postId);
      if (result.status === 'inserted') {
        announce(data.i18n.capture.thanks);
      }
    },
    [announce, data.i18n.capture.thanks, postId]
  );

  const handleCaptureDismiss = useCallback(() => {
    if (captureCompleted) {
      setCaptured(true);
    }
    setDismissed(true);
  }, [captureCompleted]);

  const ensureRef = (type: ReactionType): { current: HTMLButtonElement | null } => {
    if (!buttonRefs.has(type)) {
      buttonRefs.set(type, { current: null });
    }
    return buttonRefs.get(type)!;
  };

  const shouldShowCount = useCallback(
    (count: number): boolean => {
      const visibility = data.countVisibility ?? 'always';
      if (visibility === 'never') {
        return false;
      }
      if (visibility === 'threshold') {
        return count >= (data.countThreshold ?? 0);
      }
      return true;
    },
    [data.countThreshold, data.countVisibility]
  );

  const showCapture =
    (!captured || captureCompleted) &&
    !dismissed &&
    !previewOnly &&
    activeType !== null &&
    isPositive(activeType, data) &&
    !pending;

  useEffect(() => {
    if (!showCapture || LazyCaptureForm !== null || previewOnly) return;
    let cancelled = false;
    import('./CaptureForm')
      .then((mod) => { if (!cancelled) setLazyCaptureForm(() => mod.CaptureForm); })
      .catch(() => { /* network blip — next showCapture render will retry */ });
    return () => { cancelled = true; };
  }, [showCapture, LazyCaptureForm, previewOnly]);

  const design = data.widgetDesign ?? 'minimal';
  const maxCount = Math.max(1, ...data.reactions.map((type) => counts[type] ?? 0));
  const totalCount = data.reactions.reduce((sum, type) => sum + (counts[type] ?? 0), 0);
  const clapType = (data.positiveReactions[0] ?? data.reactions[0] ?? 'love') as ReactionType;
  const clapCount = counts[clapType] ?? totalCount;

  return (
    <div
      class="pulsepress-bar"
      data-loading={pending ? 'true' : 'false'}
      data-design={design}
      data-icon-style={data.iconStyle ?? 'classic'}
      data-theme={data.themeMode ?? 'auto'}
      data-animation={data.animationMode ?? 'subtle'}
      data-preview={previewOnly ? 'true' : 'false'}
      data-guest-reactions={data.allowGuestReactions === false ? 'false' : 'true'}
      data-reacted={activeType !== null ? 'true' : 'false'}
      style={data.primaryColor ? `--pulsepress-accent:${data.primaryColor}` : undefined}
    >
      {design === 'clap_counter' ? (
        <div class="pulsepress-clap" role="group" aria-label={data.i18n.groupLabel}>
          <button
            ref={(node: HTMLButtonElement | null) => { ensureRef(clapType).current = node; }}
            type="button"
            class="pulsepress-clap-button"
            data-active={activeType === clapType ? 'true' : 'false'}
            aria-pressed={activeType === clapType ? 'true' : 'false'}
            aria-label={`Clap${shouldShowCount(clapCount) ? ', ' + clapCount : ''}${activeType === clapType ? data.i18n.activeSuffix : ''}`}
            disabled={reactionsDisabled}
            onClick={() => handleClick(clapType)}
          >
            <span class="pulsepress-clap-icon" aria-hidden="true">👏</span>
            {shouldShowCount(clapCount) && <span class="pulsepress-clap-count" aria-hidden="true">{formatCompactCount(clapCount)}</span>}
            <span class="pulsepress-clap-label" aria-hidden="true">Claps recorded</span>
          </button>
          <p class="pulsepress-clap-helper">Press to celebrate this post</p>
        </div>
      ) : (
        <div class="pulsepress-buttons" role="group" aria-label={data.i18n.groupLabel}>
          {data.reactions.map((type) => {
            const isActive = activeType === type;
            const count = counts[type] ?? 0;
            const percent = Math.round((count / maxCount) * 100);
            const ref = ensureRef(type);
            return (
              <button
                key={type}
                ref={(node: HTMLButtonElement | null) => { ref.current = node; }}
                type="button"
                class="pulsepress-reaction"
                data-active={isActive ? 'true' : 'false'}
                data-positive={isPositive(type, data) ? 'true' : 'false'}
                aria-pressed={isActive ? 'true' : 'false'}
                aria-label={`${labelFor(type)}${shouldShowCount(count) ? ', ' + count : ''}${isActive ? data.i18n.activeSuffix : ''}`}
                disabled={reactionsDisabled}
                onClick={() => handleClick(type)}
                style={`--pulsepress-percent:${percent}%`}
              >
                <span class="pulsepress-fill" aria-hidden="true" />
                <span class="pulsepress-icon" dangerouslySetInnerHTML={{ __html: iconFor(type, data.iconStyle ?? 'classic') }} />
                <span class="pulsepress-label-text" aria-hidden="true">{labelFor(type)}</span>
                {shouldShowCount(count) && <span class="pulsepress-count" aria-hidden="true">{count}</span>}
              </button>
            );
          })}
        </div>
      )}
      <p class="pulsepress-sr-only" role="status" aria-live="polite" aria-atomic="true">{announcement}</p>
      {error !== null && (
        <p class="pulsepress-error" role="alert">{error}</p>
      )}
      {reactionsDisabled && (
        <p class="pulsepress-login-required" role="status">Please sign in to react.</p>
      )}
      {showCapture && activeType !== null && (
        LazyCaptureForm !== null ? (
          <LazyCaptureForm
            postId={postId}
            reactionType={activeType}
            data={data}
            triggerRef={ensureRef(activeType)}
            onDone={handleCaptureDone}
            onDismiss={handleCaptureDismiss}
          />
        ) : (
          <p
            class="pulsepress-capture-loading"
            role="status"
            aria-live="polite"
          >
            {data.i18n.capture.submitting}
          </p>
        )
      )}
    </div>
  );
}

function formatCompactCount(count: number): string {
  if (count >= 1000) {
    return `${Math.round(count / 100) / 10}k`;
  }
  return String(count);
}
