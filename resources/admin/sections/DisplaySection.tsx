import { CheckboxListField } from '../components/fields/CheckboxListField';
import { ColorField } from '../components/fields/ColorField';
import { NumberField } from '../components/fields/NumberField';
import { RadioField } from '../components/fields/RadioField';
import { TextField } from '../components/fields/TextField';
import { StatusPill } from '../components/StatusPill';
import type { UseSettingsState } from '../hooks/useSettingsState';
import type { MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  state: UseSettingsState;
  adminData: MoonfarmerReactionsLeadCaptureAdminData;
}

export function DisplaySection({ state, adminData }: Props) {
  const i18n = adminData.i18n;
  const { settings, choices, fieldStatus, errors, update } = state;
  const excludedPostIds = settings.hide_on_post_ids ?? [];

  const radioOpts = (key: keyof typeof choices) =>
    (choices[key] as string[]).map((value) => ({
      value,
      label: (i18n.fields as Record<string, Record<string, string>>)[`${labelMapKey(key)}`]?.[value] ?? value,
    }));

  return (
    <section class="moonfarmer-reactions-lead-capture-section" aria-labelledby="moonfarmer-reactions-lead-capture-section-display-title">
      <header class="moonfarmer-reactions-lead-capture-section__header">
        <h2 id="moonfarmer-reactions-lead-capture-section-display-title">{i18n.sections.displayTitle}</h2>
        <p class="moonfarmer-reactions-lead-capture-section__helper">{i18n.sections.displayHelper}</p>
      </header>

      <div class="moonfarmer-reactions-lead-capture-section__body">
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

        <ColorField
          label={i18n.fields.primaryColorLabel}
          helper={i18n.fields.primaryColorHelper}
          value={settings.primary_color}
          onChange={(next) => update('primary_color', next)}
          status={<StatusPill status={fieldStatus.primary_color === 'error' ? undefined : fieldStatus.primary_color} i18n={i18n} />}
          error={errors.primary_color}
        />

        <RadioField
          label={i18n.fields.animationModeLabel}
          helper={i18n.fields.animationModeHelper}
          value={settings.animation_mode}
          options={choices.animation_mode.map((v) => ({ value: v as typeof settings.animation_mode, label: i18n.fields.animationModeChoices[v] ?? v }))}
          onChange={(next) => update('animation_mode', next)}
          status={<StatusPill status={fieldStatus.animation_mode === 'error' ? undefined : fieldStatus.animation_mode} i18n={i18n} />}
          error={errors.animation_mode}
        />

        <CheckboxListField
          label={i18n.fields.autoInsertPostTypesLabel}
          helper={i18n.fields.autoInsertPostTypesHelper}
          values={settings.auto_insert_post_types}
          options={Object.entries(choices.post_types ?? {}).map(([value, label]) => ({ value, label }))}
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

        <CheckboxListField
          label={i18n.fields.hideOnPostTypesLabel}
          helper={i18n.fields.hideOnPostTypesHelper}
          values={settings.hide_on_post_types ?? []}
          options={Object.entries(choices.post_types ?? {}).map(([value, label]) => ({ value, label }))}
          onChange={(next) => update('hide_on_post_types', next)}
          status={<StatusPill status={fieldStatus.hide_on_post_types === 'error' ? undefined : fieldStatus.hide_on_post_types} i18n={i18n} />}
          error={errors.hide_on_post_types}
        />

        <TextField
          label={i18n.fields.hideOnPostIdsLabel}
          helper={i18n.fields.hideOnPostIdsHelper}
          value={excludedPostIds.join(', ')}
          placeholder={i18n.fields.hideOnPostIdsPlaceholder}
          onChange={(next) => update('hide_on_post_ids', parsePostIds(next))}
          status={<StatusPill status={fieldStatus.hide_on_post_ids === 'error' ? undefined : fieldStatus.hide_on_post_ids} i18n={i18n} />}
          error={errors.hide_on_post_ids}
        />

        {Object.keys(choices.posts ?? {}).length > 0 && (
          <label class="moonfarmer-reactions-lead-capture-field">
            <span class="moonfarmer-reactions-lead-capture-field__head">
              <span class="moonfarmer-reactions-lead-capture-field__label">{i18n.fields.hideOnPostIdsSelectLabel}</span>
            </span>
            <select
              class="moonfarmer-reactions-lead-capture-input"
              value=""
              onChange={(event) => {
                const value = parseInt((event.target as HTMLSelectElement).value, 10);
                if (Number.isFinite(value) && value > 0 && !excludedPostIds.includes(value)) {
                  void update('hide_on_post_ids', [...excludedPostIds, value]);
                }
              }}
            >
              <option value="">{i18n.fields.hideOnPostIdsSelectOption}</option>
              {Object.entries(choices.posts)
                .filter(([id]) => !excludedPostIds.includes(parseInt(id, 10)))
                .map(([id, label]) => (
                  <option key={id} value={id}>{label}</option>
                ))}
            </select>
          </label>
        )}
      </div>
    </section>
  );
}

function parsePostIds(value: string): number[] {
  const ids: number[] = [];
  value
    .split(/[\s,]+/)
    .map((part) => parseInt(part, 10))
    .forEach((id) => {
      if (Number.isFinite(id) && id > 0 && !ids.includes(id)) {
        ids.push(id);
      }
    });
  return ids;
}

function labelMapKey(key: string): string {
  const map: Record<string, string> = {
    count_visibility: 'countVisibilityChoices',
    widget_design: 'widgetDesignChoices',
    icon_style: 'iconStyleChoices',
    theme_mode: 'themeModeChoices',
    animation_mode: 'animationModeChoices',
    auto_insert_position: 'autoInsertPositionChoices',
  };
  return map[key] ?? '';
}
