import { CheckboxListField } from '../components/fields/CheckboxListField';
import { ToggleField } from '../components/fields/ToggleField';
import { StatusPill } from '../components/StatusPill';
import type { UseSettingsState } from '../hooks/useSettingsState';
import type { MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  state: UseSettingsState;
  adminData: MoonfarmerReactionsLeadCaptureAdminData;
}

export function ReactionsSection({ state, adminData }: Props) {
  const i18n = adminData.i18n;
  const { settings, reactions, fieldStatus, errors, update, updateMany } = state;
  const visibleReactionOptions = settings.enabled_reactions.map((r) => ({ value: r, label: i18n.fields.reactionLabels[r] ?? r }));

  return (
    <section class="moonfarmer-reactions-lead-capture-section" aria-labelledby="moonfarmer-reactions-lead-capture-section-reactions-title">
      <header class="moonfarmer-reactions-lead-capture-section__header">
        <h2 id="moonfarmer-reactions-lead-capture-section-reactions-title">{i18n.sections.reactionsTitle}</h2>
        <p class="moonfarmer-reactions-lead-capture-section__helper">{i18n.sections.reactionsHelper}</p>
      </header>

      <div class="moonfarmer-reactions-lead-capture-section__body">
        <CheckboxListField
          label={i18n.fields.enabledReactionsLabel}
          helper={i18n.fields.enabledReactionsHelper}
          values={settings.enabled_reactions}
          options={reactions.map((r) => ({ value: r, label: i18n.fields.reactionLabels[r] ?? r }))}
          onChange={(next) => {
            const enabled = next.length === 0 ? ['love'] : next;
            const positives = settings.positive_reactions.filter((reaction) => enabled.includes(reaction));
            return updateMany({
              enabled_reactions: enabled,
              positive_reactions: positives.length === 0 ? enabled.slice(0, 1) : positives,
            });
          }}
          status={<StatusPill status={fieldStatus.enabled_reactions === 'error' ? undefined : fieldStatus.enabled_reactions} i18n={i18n} />}
          error={errors.enabled_reactions}
        />

        <CheckboxListField
          label={i18n.fields.positiveReactionsLabel}
          helper={i18n.fields.positiveReactionsHelper}
          values={settings.positive_reactions}
          options={visibleReactionOptions}
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
          labels={i18n.toggle}
        />
      </div>
    </section>
  );
}
