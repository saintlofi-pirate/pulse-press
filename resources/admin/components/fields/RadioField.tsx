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
      class="moonfarmer-reactions-lead-capture-field moonfarmer-reactions-lead-capture-field--radio"
      aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
    >
      <div class="moonfarmer-reactions-lead-capture-field__head">
        <legend class="moonfarmer-reactions-lead-capture-field__label">{label}</legend>
        {status}
      </div>
      <div class="moonfarmer-reactions-lead-capture-radio-group" role="radiogroup">
        {options.map((opt) => (
          <label key={opt.value} class="moonfarmer-reactions-lead-capture-radio">
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
      {helper !== undefined && <p class="moonfarmer-reactions-lead-capture-field__helper" id={helpId}>{helper}</p>}
      {error && <p class="moonfarmer-reactions-lead-capture-field__error" id={errId} role="alert">{error}</p>}
    </fieldset>
  );
}
