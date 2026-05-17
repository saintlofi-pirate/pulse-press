import { useMemo } from 'preact/hooks';
import { ReactionBar } from '../../widget/components/ReactionBar';
import type { PulsePressData } from '../../widget/types';
import type { PulsePressAdminData, SettingsState } from '../types';
import '../../widget/widget.css';

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
  const i18n = adminData.i18n;

  const data = useMemo<PulsePressData>(() => {
    return {
      root: adminData.restRoot,
      nonce: adminData.nonce,
      postId: 0,
      reactions: settings.enabled_reactions,
      positiveReactions: settings.positive_reactions,
      allowGuestReactions: settings.allow_guest_reactions,
      iconStyle: settings.icon_style,
      themeMode: settings.theme_mode,
      primaryColor: settings.primary_color,
      widgetDesign: settings.widget_design,
      animationMode: settings.animation_mode,
      countVisibility: settings.count_visibility,
      countThreshold: settings.count_threshold,
      i18n: {
        loading: 'Preview',
        error: 'Preview error',
        activeSuffix: ', selected',
        groupLabel: 'Preview reactions',
        announceReacted: 'Preview only',
        announceUpdated: 'Preview only',
        capture: {
          prompt: 'Get future post updates',
          label: 'Email address',
          placeholder: 'you@example.com',
          consent: settings.consent_text,
          consentHelper: 'Helper text appears here.',
          submit: 'Subscribe',
          submitting: 'Submitting…',
          thanks: 'You are subscribed. Thanks - we will send future post updates.',
          alreadyCaptured: 'Already saved.',
          networkError: 'Network error.',
          expiredNonce: 'Session expired.',
          dismiss: 'Dismiss',
        },
      },
    };
  }, [adminData, settings.allow_guest_reactions, settings.animation_mode, settings.consent_text, settings.count_threshold, settings.count_visibility, settings.enabled_reactions, settings.icon_style, settings.positive_reactions, settings.primary_color, settings.theme_mode, settings.widget_design]);

  return (
    <aside class="pulsepress-preview" aria-labelledby="pulsepress-preview-title" data-theme={settings.theme_mode} data-design={settings.widget_design} data-icon-style={settings.icon_style} data-animation={settings.animation_mode}>
      <div class="pulsepress-preview__header">
        <h2 id="pulsepress-preview-title" class="pulsepress-preview__title">{i18n.livePreviewLabel}</h2>
        <p class="pulsepress-preview__helper">{i18n.livePreviewHelper}</p>
      </div>
      <div class="pulsepress-preview__stage">
        <div class="pulsepress">
          <ReactionBar postId={0} data={{ ...data, postId: 0 }} initialCounts={MOCK_COUNTS} previewOnly />
        </div>
        <div class="pulsepress-preview__overlay" aria-hidden="true" />
      </div>
    </aside>
  );
}
