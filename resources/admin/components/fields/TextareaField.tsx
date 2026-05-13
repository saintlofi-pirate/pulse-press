import type { ComponentChildren } from 'preact';
import { useId } from 'preact/hooks';
import { FieldShell } from './Field';

interface Props {
  label: ComponentChildren;
  helper?: ComponentChildren;
  value: string;
  onChange: (next: string) => void;
  status?: ComponentChildren;
  error?: string | null;
  rows?: number;
  maxLength?: number;
}

export function TextareaField({ label, helper, value, onChange, status, error, rows = 4, maxLength }: Props) {
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
      <textarea
        id={id}
        class="pulsepress-input pulsepress-textarea"
        rows={rows}
        maxLength={maxLength}
        value={value}
        aria-invalid={error ? 'true' : 'false'}
        aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
        onInput={(e) => onChange((e.target as HTMLTextAreaElement).value)}
      />
    </FieldShell>
  );
}
