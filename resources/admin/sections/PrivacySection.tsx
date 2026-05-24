import { ToggleField } from '../components/fields/ToggleField';
import { StatusPill } from '../components/StatusPill';
import type { UseSettingsState } from '../hooks/useSettingsState';
import type { MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  state: UseSettingsState;
  adminData: MoonfarmerReactionsLeadCaptureAdminData;
}

export function PrivacySection({ state, adminData }: Props) {
  const i18n = adminData.i18n;
  const { settings, fieldStatus, errors, update } = state;

  return (
    <section class="moonfarmer-reactions-lead-capture-section" aria-labelledby="moonfarmer-reactions-lead-capture-section-privacy-title">
      <header class="moonfarmer-reactions-lead-capture-section__header">
        <h2 id="moonfarmer-reactions-lead-capture-section-privacy-title">{i18n.sections.privacyTitle}</h2>
        <p class="moonfarmer-reactions-lead-capture-section__helper">{i18n.sections.privacyHelper}</p>
      </header>

      <div class="moonfarmer-reactions-lead-capture-section__body">
        <ToggleField
          label={i18n.fields.deleteOnUninstallLabel}
          helper={i18n.fields.deleteOnUninstallHelper}
          checked={settings.delete_on_uninstall}
          onChange={(next) => update('delete_on_uninstall', next)}
          status={<StatusPill status={fieldStatus.delete_on_uninstall === 'error' ? undefined : fieldStatus.delete_on_uninstall} i18n={i18n} />}
          error={errors.delete_on_uninstall}
          labels={i18n.toggle}
        />
      </div>
    </section>
  );
}
