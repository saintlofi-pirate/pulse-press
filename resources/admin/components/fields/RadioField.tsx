import type { ComponentChildren } from 'preact';
import { useId } from 'preact/hooks';

interface Props<T extends string> {
  label: ComponentChildren;
  helper?: ComponentChildren;
  value: T;
  options: Array<{ value: T; label: ComponentChildren }>;
  onChange: (next: T) => void;
  status?: ComponentChildren;
  error?: string | null;
}

export function RadioField<T extends string>({ label, helper, value, options, onChange, status, error }: Props<T>) {
  const id     = useId();
  const helpId = `${id}-help`;
  const errId  = `${id}-error`;
  return (
    <fieldset
      class="pulsepress-field pulsepress-field--radio"
      aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
    >
      <div class="pulsepress-field__head">
        <legend class="pulsepress-field__label">{label}</legend>
        {status}
      </div>
      <div class="pulsepress-radio-group" role="radiogroup">
        {options.map((opt) => (
          <label key={opt.value} class="pulsepress-radio">
            <input
              type="radio"
              name={id}
              value={opt.value}
              checked={value === opt.value}
              onChange={() => onChange(opt.value)}
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
