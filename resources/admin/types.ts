import type { AnimationMode, PulsePressData, ReactionType, WidgetDesign } from '../widget/types';

export type SettingsState = {
  count_visibility: 'always' | 'never' | 'threshold';
  count_threshold: number;
  widget_design: WidgetDesign;
  icon_style: 'classic' | 'emoji';
  theme_mode: 'light' | 'dark' | 'auto';
  primary_color: string;
  animation_mode: AnimationMode;
  auto_insert_post_types: string[];
  auto_insert_position: 'above' | 'below' | 'both';
  enabled_reactions: ReactionType[];
  positive_reactions: ReactionType[];
  allow_guest_reactions: boolean;
  consent_text: string;
  consent_text_version: string;
  delete_on_uninstall: boolean;
  retention_days: number;
  hide_on_post_types: string[];
  hide_on_post_ids: number[];
};

export type SettingsChoices = {
  count_visibility: string[];
  widget_design: string[];
  icon_style: string[];
  theme_mode: string[];
  animation_mode: string[];
  auto_insert_position: string[];
  post_types: Record<string, string>;
  posts: Record<string, string>;
};

export interface ExtensionTab {
  id: string;
  label: string;
  order: number;
}

export interface ExtensionMetricCard {
  id: string;
  title: string;
  value: string;
  helper?: string;
  emphasis?: boolean;
  renderJs?: string;
  data?: unknown;
  fallback?: string;
}

export interface ExtensionAnalyticsPanel {
  id: string;
  title?: string;
  helper?: string;
  data?: unknown;
  renderJs?: string;
  fallback?: string;
}

export interface PulsePressAdminData {
  restRoot: string;
  nonce: string;
  settings: SettingsState;
  defaults: SettingsState;
  choices: SettingsChoices;
  schemaVersion: number;
  reactions: ReactionType[];
  version: string;
  tabs: ExtensionTab[];
  metricCards: ExtensionMetricCard[];
  analyticsPanels: ExtensionAnalyticsPanel[];
  i18n: {
    pageTitle: string;
    saved: string;
    saving: string;
    saveError: string;
    resetSection: string;
    livePreviewLabel: string;
    livePreviewHelper: string;
    livePreviewReadOnly: string;
    tabs: { display: string; analytics: string; reactions: string; capture: string; privacy: string } & Record<string, string>;
    extension: { fallback: string; sectionLabel: string };
    toggle: { on: string; off: string };
    sections: {
      displayTitle: string;
      displayHelper: string;
      reactionsTitle: string;
      reactionsHelper: string;
      captureTitle: string;
      captureHelper: string;
      privacyTitle: string;
      privacyHelper: string;
      analyticsTitle: string;
      analyticsHelper: string;
    };
    analytics: {
      totalReactionsLabel: string;
      totalReactionsHelper: string;
      totalCapturesLabel: string;
      totalCapturesHelper: string;
      sentimentRateLabel: string;
      sentimentRateHelper: string;
      captureRateLabel: string;
      captureRateHelper: string;
      topPostsCaption: string;
      topPostsColumns: { post: string; total: string; positive: string; captures: string };
      sentimentInsightTemplate: string;
      sentimentInsightFallback: string;
      chartLabel: string;
      emptyState: string;
      loadingState: string;
      errorState: string;
      retry: string;
      clampedNotice: string;
      deletedPost: string;
    };
    fields: {
      countVisibilityLabel: string;
      countVisibilityHelper: string;
      countVisibilityChoices: Record<string, string>;
      countThresholdLabel: string;
      countThresholdHelper: string;
      widgetDesignLabel: string;
      widgetDesignHelper: string;
      widgetDesignChoices: Record<string, string>;
      iconStyleLabel: string;
      iconStyleHelper: string;
      iconStyleChoices: Record<string, string>;
      themeModeLabel: string;
      themeModeHelper: string;
      themeModeChoices: Record<string, string>;
      primaryColorLabel: string;
      primaryColorHelper: string;
      animationModeLabel: string;
      animationModeHelper: string;
      animationModeChoices: Record<string, string>;
      autoInsertPostTypesLabel: string;
      autoInsertPostTypesHelper: string;
      autoInsertPositionLabel: string;
      autoInsertPositionHelper: string;
      autoInsertPositionChoices: Record<string, string>;
      hideOnPostTypesLabel: string;
      hideOnPostTypesHelper: string;
      hideOnPostIdsLabel: string;
      hideOnPostIdsHelper: string;
      hideOnPostIdsPlaceholder: string;
      hideOnPostIdsSelectLabel: string;
      hideOnPostIdsSelectOption: string;
      enabledReactionsLabel: string;
      enabledReactionsHelper: string;
      positiveReactionsLabel: string;
      positiveReactionsHelper: string;
      reactionLabels: Record<string, string>;
      allowGuestReactionsLabel: string;
      allowGuestReactionsHelper: string;
      consentTextLabel: string;
      consentTextHelper: string;
      consentVersionLabel: string;
      consentVersionHelper: string;
      retentionDaysLabel: string;
      retentionDaysHelper: string;
      deleteOnUninstallLabel: string;
      deleteOnUninstallHelper: string;
    };
  };
}

export type TabId = 'display' | 'reactions' | 'capture' | 'privacy';

export type ExtensionKind = 'tab' | 'card' | 'panel';

export interface ExtensionContext {
  id: string;
  kind: ExtensionKind;
  data: unknown;
  adminData: PulsePressAdminData;
}

export type ExtensionRenderer = (root: HTMLElement, ctx: ExtensionContext) => void | (() => void);

export interface PulsePressAdminApi {
  registerTabRenderer: (id: string, renderer: ExtensionRenderer) => void;
  registerCardRenderer: (id: string, renderer: ExtensionRenderer) => void;
  registerPanelRenderer: (id: string, renderer: ExtensionRenderer) => void;
}

declare global {
  interface Window {
    PulsePressAdminData?: PulsePressAdminData;
    PulsePressAdmin?: PulsePressAdminApi;
  }
}

// Re-export front-end types so admin files can pull from one place.
export type { AnimationMode, PulsePressData, ReactionType, WidgetDesign };
