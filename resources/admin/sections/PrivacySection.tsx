import { ToggleField } from '../components/fields/ToggleField';
import { StatusPill } from '../components/StatusPill';
import type { UseSettingsState } from '../hooks/useSettingsState';
import type { PulsePressAdminData } from '../types';

interface Props {
  state: UseSettingsState;
  adminData: PulsePressAdminData;
}

export function PrivacySection({ state, adminData }: Props) {
  const i18n = adminData.i18n;
  const { settings, fieldStatus, errors, update } = state;

  return (
    <section class="pulsepress-section" aria-labelledby="pulsepress-section-privacy-title">
      <header class="pulsepress-section__header">
        <h2 id="pulsepress-section-privacy-title">{i18n.sections.privacyTitle}</h2>
        <p class="pulsepress-section__helper">{i18n.sections.privacyHelper}</p>
      </header>

      <div class="pulsepress-section__body">
        <ToggleField
          label={i18n.fields.deleteOnUninstallLabel}
          helper={i18n.fields.deleteOnUninstallHelper}
          checked={settings.delete_on_uninstall}
          onChange={(next) => update('delete_on_uninstall', next)}
          status={<StatusPill status={fieldStatus.delete_on_uninstall === 'error' ? undefined : fieldStatus.delete_on_uninstall} i18n={i18n} />}
          error={errors.delete_on_uninstall}
        />
      </div>
    </section>
  );
}
