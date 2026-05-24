import { CaptureExportButton } from '../components/CaptureExportButton';
import { NumberField } from '../components/fields/NumberField';
import { TextField } from '../components/fields/TextField';
import { TextareaField } from '../components/fields/TextareaField';
import { StatusPill } from '../components/StatusPill';
import type { UseSettingsState } from '../hooks/useSettingsState';
import type { MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  state: UseSettingsState;
  adminData: MoonfarmerReactionsLeadCaptureAdminData;
}

export function CaptureSection({ state, adminData }: Props) {
  const i18n = adminData.i18n;
  const { settings, fieldStatus, errors, update } = state;

  return (
    <section class="moonfarmer-reactions-lead-capture-section" aria-labelledby="moonfarmer-reactions-lead-capture-section-capture-title">
      <header class="moonfarmer-reactions-lead-capture-section__header">
        <h2 id="moonfarmer-reactions-lead-capture-section-capture-title">{i18n.sections.captureTitle}</h2>
        <p class="moonfarmer-reactions-lead-capture-section__helper">{i18n.sections.captureHelper}</p>
      </header>

      <div class="moonfarmer-reactions-lead-capture-section__body">
        <TextareaField
          label={i18n.fields.consentTextLabel}
          helper={i18n.fields.consentTextHelper}
          value={settings.consent_text}
          maxLength={2000}
          onChange={(next) => update('consent_text', next)}
          status={<StatusPill status={fieldStatus.consent_text === 'error' ? undefined : fieldStatus.consent_text} i18n={i18n} />}
          error={errors.consent_text}
        />

        <TextField
          label={i18n.fields.consentVersionLabel}
          helper={i18n.fields.consentVersionHelper}
          value={settings.consent_text_version}
          maxLength={32}
          onChange={(next) => update('consent_text_version', next)}
          status={<StatusPill status={fieldStatus.consent_text_version === 'error' ? undefined : fieldStatus.consent_text_version} i18n={i18n} />}
          error={errors.consent_text_version}
        />

        <NumberField
          label={i18n.fields.retentionDaysLabel}
          helper={i18n.fields.retentionDaysHelper}
          value={settings.retention_days}
          min={0}
          max={3650}
          onChange={(next) => update('retention_days', next)}
          status={<StatusPill status={fieldStatus.retention_days === 'error' ? undefined : fieldStatus.retention_days} i18n={i18n} />}
          error={errors.retention_days}
        />

        <CaptureExportButton adminData={adminData} />
      </div>
    </section>
  );
}
