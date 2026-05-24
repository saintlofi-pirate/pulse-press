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
      class="moonfarmer-reactions-lead-capture-field moonfarmer-reactions-lead-capture-field--checklist"
      aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
    >
      <div class="moonfarmer-reactions-lead-capture-field__head">
        <legend class="moonfarmer-reactions-lead-capture-field__label">{label}</legend>
        {status}
      </div>
      <div class="moonfarmer-reactions-lead-capture-checklist">
        {options.map((opt) => (
          <label key={opt.value} class="moonfarmer-reactions-lead-capture-check">
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
      {helper !== undefined && <p class="moonfarmer-reactions-lead-capture-field__helper" id={helpId}>{helper}</p>}
      {error && <p class="moonfarmer-reactions-lead-capture-field__error" id={errId} role="alert">{error}</p>}
    </fieldset>
  );
}
