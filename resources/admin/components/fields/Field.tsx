import type { ComponentChildren } from 'preact';

interface FieldShellProps {
  label: ComponentChildren;
  helper?: ComponentChildren;
  error?: string | null;
  status?: ComponentChildren;
  htmlFor?: string;
  describedById?: string;
  errorId?: string;
  children: ComponentChildren;
}

export function FieldShell({ label, helper, error, status, htmlFor, describedById, errorId, children }: FieldShellProps) {
  return (
    <div class="pulsepress-field">
      <div class="pulsepress-field__head">
        {htmlFor !== undefined ? (
          <label class="pulsepress-field__label" for={htmlFor}>{label}</label>
        ) : (
          <span class="pulsepress-field__label">{label}</span>
        )}
        {status}
      </div>
      {children}
      {helper !== undefined && (
        <p class="pulsepress-field__helper" id={describedById}>{helper}</p>
      )}
      {error && (
        <p class="pulsepress-field__error" id={errorId} role="alert">{error}</p>
      )}
    </div>
  );
}
