import type { ComponentChildren } from 'preact';
import { useId } from 'preact/hooks';

interface Props {
  label: ComponentChildren;
  helper?: ComponentChildren;
  values: string[];
  options: Array<{ value: string; label: ComponentChildren }>;
  onChange: (next: string[]) => void;
  status?: ComponentChildren;
  error?: string | null;
}

export function CheckboxListField({ label, helper, values, options, onChange, status, error }: Props) {
  const id     = useId();
  const helpId = `${id}-help`;
  const errId  = `${id}-error`;

  const toggle = (value: string, checked: boolean) => {
    const next = checked
      ? Array.from(new Set([...values, value]))
      : values.filter((v) => v !== value);
    onChange(next);
  };

  return (
    <fieldset
      class="pulsepress-field pulsepress-field--checklist"
      aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
    >
      <div class="pulsepress-field__head">
        <legend class="pulsepress-field__label">{label}</legend>
        {status}
      </div>
      <div class="pulsepress-checklist">
        {options.map((opt) => (
          <label key={opt.value} class="pulsepress-check">
            <input
              type="checkbox"
              value={opt.value}
              checked={values.includes(opt.value)}
              onChange={(e) => toggle(opt.value, (e.target as HTMLInputElement).checked)}
            />
            <span>{opt.label}</span>
          </label>
        ))}
      </div>
      {helper !== undefined && <p class="pulsepress-field__helper" id={helpId}>{helper}</p>}
      {error && <p class="pulsepress-field__error" id={errId} role="alert">{error}</p>}
    </fieldset>
  );
}
