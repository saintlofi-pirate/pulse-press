import { CheckboxListField } from '../components/fields/CheckboxListField';
import { NumberField } from '../components/fields/NumberField';
import { RadioField } from '../components/fields/RadioField';
import { StatusPill } from '../components/StatusPill';
import type { UseSettingsState } from '../hooks/useSettingsState';
import type { PulsePressAdminData } from '../types';

interface Props {
  state: UseSettingsState;
  adminData: PulsePressAdminData;
}

export function DisplaySection({ state, adminData }: Props) {
  const i18n = adminData.i18n;
  const { settings, choices, fieldStatus, errors, update } = state;

  const radioOpts = (key: keyof typeof choices) =>
    (choices[key] as string[]).map((value) => ({
      value,
      label: (i18n.fields as Record<string, Record<string, string>>)[`${labelMapKey(key)}`]?.[value] ?? value,
    }));

  return (
    <section class="pulsepress-section" aria-labelledby="pulsepress-section-display-title">
      <header class="pulsepress-section__header">
        <h2 id="pulsepress-section-display-title">{i18n.sections.displayTitle}</h2>
        <p class="pulsepress-section__helper">{i18n.sections.displayHelper}</p>
      </header>

      <div class="pulsepress-section__body">
        <RadioField
          label={i18n.fields.countVisibilityLabel}
          helper={i18n.fields.countVisibilityHelper}
          value={settings.count_visibility}
          options={choices.count_visibility.map((v) => ({ value: v as typeof settings.count_visibility, label: i18n.fields.countVisibilityChoices[v] ?? v }))}
          onChange={(next) => update('count_visibility', next)}
          status={<StatusPill status={fieldStatus.count_visibility === 'error' ? undefined : fieldStatus.count_visibility} i18n={i18n} />}
          error={errors.count_visibility}
        />

        {settings.count_visibility === 'threshold' && (
          <NumberField
            label={i18n.fields.countThresholdLabel}
            helper={i18n.fields.countThresholdHelper}
            value={settings.count_threshold}
            min={0}
            max={1000}
            onChange={(next) => update('count_threshold', next)}
            status={<StatusPill status={fieldStatus.count_threshold === 'error' ? undefined : fieldStatus.count_threshold} i18n={i18n} />}
            error={errors.count_threshold}
          />
        )}

        <RadioField
          label={i18n.fields.widgetDesignLabel}
          helper={i18n.fields.widgetDesignHelper}
          value={settings.widget_design}
          options={choices.widget_design.map((v) => ({ value: v as typeof settings.widget_design, label: i18n.fields.widgetDesignChoices[v] ?? v }))}
          onChange={(next) => update('widget_design', next)}
          status={<StatusPill status={fieldStatus.widget_design === 'error' ? undefined : fieldStatus.widget_design} i18n={i18n} />}
          error={errors.widget_design}
        />

        <RadioField
          label={i18n.fields.iconStyleLabel}
          helper={i18n.fields.iconStyleHelper}
          value={settings.icon_style}
          options={choices.icon_style.map((v) => ({ value: v as typeof settings.icon_style, label: i18n.fields.iconStyleChoices[v] ?? v }))}
          onChange={(next) => update('icon_style', next)}
          status={<StatusPill status={fieldStatus.icon_style === 'error' ? undefined : fieldStatus.icon_style} i18n={i18n} />}
          error={errors.icon_style}
        />

        <RadioField
          label={i18n.fields.themeModeLabel}
          helper={i18n.fields.themeModeHelper}
          value={settings.theme_mode}
          options={choices.theme_mode.map((v) => ({ value: v as typeof settings.theme_mode, label: i18n.fields.themeModeChoices[v] ?? v }))}
          onChange={(next) => update('theme_mode', next)}
          status={<StatusPill status={fieldStatus.theme_mode === 'error' ? undefined : fieldStatus.theme_mode} i18n={i18n} />}
          error={errors.theme_mode}
        />

        <CheckboxListField
          label={i18n.fields.autoInsertPostTypesLabel}
          helper={i18n.fields.autoInsertPostTypesHelper}
          values={settings.auto_insert_post_types}
          options={[
            { value: 'post', label: 'Posts' },
            { value: 'page', label: 'Pages' },
          ]}
          onChange={(next) => update('auto_insert_post_types', next.length === 0 ? ['post'] : next)}
          status={<StatusPill status={fieldStatus.auto_insert_post_types === 'error' ? undefined : fieldStatus.auto_insert_post_types} i18n={i18n} />}
          error={errors.auto_insert_post_types}
        />

        <RadioField
          label={i18n.fields.autoInsertPositionLabel}
          helper={i18n.fields.autoInsertPositionHelper}
          value={settings.auto_insert_position}
          options={choices.auto_insert_position.map((v) => ({ value: v as typeof settings.auto_insert_position, label: i18n.fields.autoInsertPositionChoices[v] ?? v }))}
          onChange={(next) => update('auto_insert_position', next)}
          status={<StatusPill status={fieldStatus.auto_insert_position === 'error' ? undefined : fieldStatus.auto_insert_position} i18n={i18n} />}
          error={errors.auto_insert_position}
        />
      </div>
    </section>
  );
}

function labelMapKey(key: string): string {
  const map: Record<string, string> = {
    count_visibility: 'countVisibilityChoices',
    widget_design: 'widgetDesignChoices',
    icon_style: 'iconStyleChoices',
    theme_mode: 'themeModeChoices',
    auto_insert_position: 'autoInsertPositionChoices',
  };
  return map[key] ?? '';
}
