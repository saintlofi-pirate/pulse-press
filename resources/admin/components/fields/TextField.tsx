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
  placeholder?: string;
  maxLength?: number;
}

export function TextField({ label, helper, value, onChange, status, error, placeholder, maxLength }: Props) {
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
        class="moonfarmer-reactions-lead-capture-input"
        type="text"
        value={value}
        placeholder={placeholder}
        maxLength={maxLength}
        aria-invalid={error ? 'true' : 'false'}
        aria-describedby={`${helpId}${error ? ' ' + errId : ''}`}
        onInput={(e) => onChange((e.target as HTMLInputElement).value)}
      />
    </FieldShell>
  );
}
