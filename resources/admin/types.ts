import type { PulsePressData, ReactionType } from '../widget/types';

export type SettingsState = {
  count_visibility: 'always' | 'never' | 'threshold';
  count_threshold: number;
  widget_design: 'minimal' | 'expressive';
  icon_style: 'classic' | 'emoji';
  theme_mode: 'light' | 'dark' | 'auto';
  auto_insert_post_types: string[];
  auto_insert_position: 'above' | 'below' | 'both';
  positive_reactions: ReactionType[];
  allow_guest_reactions: boolean;
  consent_text: string;
  consent_text_version: string;
  delete_on_uninstall: boolean;
  retention_days: number;
  hide_on_post_types: string[];
};

export type SettingsChoices = {
  count_visibility: string[];
  widget_design: string[];
  icon_style: string[];
  theme_mode: string[];
  auto_insert_position: string[];
  post_types: Record<string, string>;
};

export interface PulsePressAdminData {
  restRoot: string;
  nonce: string;
  settings: SettingsState;
  defaults: SettingsState;
  choices: SettingsChoices;
  schemaVersion: number;
  reactions: ReactionType[];
  version: string;
  i18n: {
    pageTitle: string;
    saved: string;
    saving: string;
    saveError: string;
    resetSection: string;
    livePreviewLabel: string;
    livePreviewHelper: string;
    livePreviewReadOnly: string;
    tabs: { display: string; reactions: string; capture: string; privacy: string };
    sections: {
      displayTitle: string;
      displayHelper: string;
      reactionsTitle: string;
      reactionsHelper: string;
      captureTitle: string;
      captureHelper: string;
      privacyTitle: string;
      privacyHelper: string;
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
      autoInsertPostTypesLabel: string;
      autoInsertPostTypesHelper: string;
      autoInsertPositionLabel: string;
      autoInsertPositionHelper: string;
      autoInsertPositionChoices: Record<string, string>;
      hideOnPostTypesLabel: string;
      hideOnPostTypesHelper: string;
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

declare global {
  interface Window {
    PulsePressAdminData?: PulsePressAdminData;
  }
}

// Re-export front-end types so admin files can pull from one place.
export type { PulsePressData, ReactionType };
