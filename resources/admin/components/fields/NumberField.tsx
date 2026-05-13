import type { ComponentChildren } from 'preact';
import { useId } from 'preact/hooks';
import { FieldShell } from './Field';

interface Props {
  label: ComponentChildren;
  helper?: ComponentChildren;
  value: number;
  onChange: (next: number) => void;
  status?: ComponentChildren;
  error?: string | null;
  min?: number;
  max?: number;
  step?: number;
}

export function NumberField({ label, helper, value, onChange, status, error, min, max, step = 1 }: Props) {
  const id     = useId();
  const helpId = `${id}-help`;
  const errId  = `${id}-error`;
  return (
    <FieldShell
      label={label}
      helper={helper}
      error={error}
      status={status}
      htmlFor={id}
      describedById={helpId}
      errorId={errId}
    >
      <input
        id={id}
        class="pulsepress-input pulsepress-number"
        type="number"
        value={Number.isFinite(value) ? value : 0}
        min={min}
        max={max}
        step={step}
        aria-invalid={error ? 'true' : 'false'}
        aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
        onInput={(e) => {
          const raw = (e.target as HTMLInputElement).value;
          const parsed = raw === '' ? 0 : parseInt(raw, 10);
          onChange(Number.isFinite(parsed) ? parsed : 0);
        }}
      />
    </FieldShell>
  );
}
