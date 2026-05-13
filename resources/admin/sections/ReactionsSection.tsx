import { CheckboxListField } from '../components/fields/CheckboxListField';
import { ToggleField } from '../components/fields/ToggleField';
import { StatusPill } from '../components/StatusPill';
import type { UseSettingsState } from '../hooks/useSettingsState';
import type { PulsePressAdminData } from '../types';

interface Props {
  state: UseSettingsState;
  adminData: PulsePressAdminData;
}

export function ReactionsSection({ state, adminData }: Props) {
  const i18n = adminData.i18n;
  const { settings, reactions, fieldStatus, errors, update } = state;

  return (
    <section class="pulsepress-section" aria-labelledby="pulsepress-section-reactions-title">
      <header class="pulsepress-section__header">
        <h2 id="pulsepress-section-reactions-title">{i18n.sections.reactionsTitle}</h2>
        <p class="pulsepress-section__helper">{i18n.sections.reactionsHelper}</p>
      </header>

      <div class="pulsepress-section__body">
        <CheckboxListField
          label={i18n.fields.positiveReactionsLabel}
          helper={i18n.fields.positiveReactionsHelper}
          values={settings.positive_reactions}
          options={reactions.map((r) => ({ value: r, label: i18n.fields.reactionLabels[r] ?? r }))}
          onChange={(next) => update('positive_reactions', next)}
          status={<StatusPill status={fieldStatus.positive_reactions === 'error' ? undefined : fieldStatus.positive_reactions} i18n={i18n} />}
          error={errors.positive_reactions}
        />

        <ToggleField
          label={i18n.fields.allowGuestReactionsLabel}
          helper={i18n.fields.allowGuestReactionsHelper}
          checked={settings.allow_guest_reactions}
          onChange={(next) => update('allow_guest_reactions', next)}
          status={<StatusPill status={fieldStatus.allow_guest_reactions === 'error' ? undefined : fieldStatus.allow_guest_reactions} i18n={i18n} />}
          error={errors.allow_guest_reactions}
        />
      </div>
    </section>
  );
}
