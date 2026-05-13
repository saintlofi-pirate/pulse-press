import { useMemo, useRef, useState } from 'preact/hooks';
import { ReactionBar } from '../../widget/components/ReactionBar';
import type { PulsePressData } from '../../widget/types';
import type { PulsePressAdminData, SettingsState } from '../types';

interface Props {
  settings: SettingsState;
  adminData: PulsePressAdminData;
}

const MOCK_COUNTS: Record<string, number> = {
  love: 24,
  insightful: 12,
  funny: 6,
  sad: 3,
  surprised: 2,
  angry: 1,
};

export function LivePreview({ settings, adminData }: Props) {
  const previewRoot = useRef<HTMLDivElement | null>(null);
  const [announce, setAnnounce] = useState<string>('');
  const i18n = adminData.i18n;

  const data = useMemo<PulsePressData>(() => {
    return {
      root: adminData.restRoot,
      nonce: adminData.nonce,
      postId: 0,
      reactions: adminData.reactions,
      positiveReactions: settings.positive_reactions,
      i18n: {
        loading: 'Preview',
        error: 'Preview error',
        activeSuffix: ', selected',
        groupLabel: 'Preview reactions',
        announceReacted: 'Preview only',
        announceUpdated: 'Preview only',
        capture: {
          prompt: settings.consent_text,
          label: 'Email address',
          placeholder: 'you@example.com',
          consent: settings.consent_text,
          consentHelper: 'Helper text appears here.',
          submit: 'Subscribe',
          submitting: 'Submitting…',
          thanks: 'Thanks!',
          alreadyCaptured: 'Already saved.',
          networkError: 'Network error.',
          expiredNonce: 'Session expired.',
          dismiss: 'Dismiss',
        },
      },
    };
  }, [adminData, settings.consent_text, settings.positive_reactions]);

  const handleClickCapture = (event: MouseEvent) => {
    const target = event.target as HTMLElement | null;
    if (target?.closest('button')) {
      event.preventDefault();
      event.stopPropagation();
      setAnnounce('');
      window.setTimeout(() => setAnnounce(i18n.livePreviewReadOnly), 50);
    }
  };

  return (
    <aside class="pulsepress-preview" aria-labelledby="pulsepress-preview-title" data-theme={settings.theme_mode} data-design={settings.widget_design} data-icon-style={settings.icon_style}>
      <div class="pulsepress-preview__header">
        <h2 id="pulsepress-preview-title" class="pulsepress-preview__title">{i18n.livePreviewLabel}</h2>
        <p class="pulsepress-preview__helper">{i18n.livePreviewHelper}</p>
      </div>
      <div
        ref={previewRoot}
        class="pulsepress-preview__stage"
        onClickCapture={handleClickCapture}
      >
        <div class="pulsepress">
          <ReactionBar postId={0} data={{ ...data, postId: 0 }} />
        </div>
        <div class="pulsepress-preview__overlay" aria-hidden="true" />
      </div>
      <p class="pulsepress-preview__counts">Mock counts: love {MOCK_COUNTS.love}, insightful {MOCK_COUNTS.insightful}, funny {MOCK_COUNTS.funny}, sad {MOCK_COUNTS.sad}, surprised {MOCK_COUNTS.surprised}, angry {MOCK_COUNTS.angry}.</p>
      <p class="pulsepress-sr-only" role="status" aria-live="polite" aria-atomic="true">{announce}</p>
    </aside>
  );
}
