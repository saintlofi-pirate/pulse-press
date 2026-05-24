import { useCallback, useEffect, useRef, useState } from 'preact/hooks';
import type { Ref } from 'preact';
import { postCapture, RestError } from '../api';
import type { MoonfarmerReactionsLeadCaptureData, ReactionType } from '../types';

interface Props {
  postId: number;
  reactionType: ReactionType;
  data: MoonfarmerReactionsLeadCaptureData;
  triggerRef: Ref<HTMLButtonElement>;
  onDone: (result: { status: 'inserted' | 'already_exists' }) => void;
  onDismiss: () => void;
}

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const EMAIL_INPUT_ID    = 'moonfarmer-reactions-lead-capture-capture-email';
const CONSENT_INPUT_ID  = 'moonfarmer-reactions-lead-capture-capture-consent';
const CONSENT_HELP_ID   = 'moonfarmer-reactions-lead-capture-capture-consent-help';
const CAPTURE_TITLE_ID  = 'moonfarmer-reactions-lead-capture-capture-title';

export function CaptureForm({ postId, reactionType, data, triggerRef, onDone, onDismiss }: Props) {
  const i18n = data.i18n.capture;
  const [email, setEmail] = useState('');
  const [consent, setConsent] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const emailRef   = useRef<HTMLInputElement | null>(null);
  const consentRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    emailRef.current?.focus();
  }, []);

  useEffect(() => {
    if (successMessage === null) {
      return;
    }

    const timer = window.setTimeout(() => {
      onDismiss();
    }, 3200);

    return () => window.clearTimeout(timer);
  }, [onDismiss, successMessage]);

  const restoreTriggerFocus = useCallback(() => {
    window.setTimeout(() => {
      const node = (triggerRef as { current: HTMLButtonElement | null }).current;
      node?.focus();
    }, 50);
  }, [triggerRef]);

  const finish = useCallback(
    (message: string, status: 'inserted' | 'already_exists') => {
      setSuccessMessage(message);
      onDone({ status });
      restoreTriggerFocus();
    },
    [onDone, restoreTriggerFocus]
  );

  const handleSubmit = useCallback(
    async (event: Event) => {
      event.preventDefault();
      if (submitting) return;

      if (!consent) {
        setError(i18n.consent);
        consentRef.current?.focus();
        return;
      }

      if (!EMAIL_REGEX.test(email)) {
        setError(i18n.placeholder);
        emailRef.current?.focus();
        return;
      }

      setError(null);
      setSubmitting(true);

      try {
        await postCapture(
          { root: data.root, nonce: data.nonce, postId },
          { email, reactionType, source: 'inline' }
        );
        finish(i18n.thanks, 'inserted');
      } catch (err) {
        if (err instanceof RestError) {
          if (err.code === 'moonfarmer_reactions_lead_capture_capture_already_exists') {
            finish(i18n.alreadyCaptured, 'already_exists');
            return;
          }
          if (err.code === 'rest_cookie_invalid_nonce' || err.code === 'rest_forbidden') {
            setError(i18n.expiredNonce);
          } else {
            setError(err.message || i18n.networkError);
          }
        } else {
          setError(i18n.networkError);
        }
        setSubmitting(false);
      }
    },
    [consent, data.nonce, data.root, email, finish, i18n, postId, reactionType, submitting]
  );

  const handleKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        restoreTriggerFocus();
        onDismiss();
      }
    },
    [onDismiss, restoreTriggerFocus]
  );

  if (successMessage !== null) {
    return (
      <div class="moonfarmer-reactions-lead-capture-capture moonfarmer-reactions-lead-capture-capture-success">
        <p role="status" aria-live="polite">{successMessage}</p>
        <button type="button" class="moonfarmer-reactions-lead-capture-link" onClick={onDismiss}>{i18n.dismiss}</button>
      </div>
    );
  }

  return (
    <form
      class="moonfarmer-reactions-lead-capture-capture"
      role="dialog"
      aria-modal="true"
      aria-labelledby={CAPTURE_TITLE_ID}
      onSubmit={handleSubmit}
      onKeyDown={handleKeyDown}
      noValidate
    >
      <h3 id={CAPTURE_TITLE_ID} class="moonfarmer-reactions-lead-capture-capture-title">{i18n.prompt}</h3>

      <div class="moonfarmer-reactions-lead-capture-field">
        <label class="moonfarmer-reactions-lead-capture-label" for={EMAIL_INPUT_ID}>{i18n.label}</label>
        <input
          ref={emailRef}
          id={EMAIL_INPUT_ID}
          class="moonfarmer-reactions-lead-capture-input"
          type="email"
          autocomplete="email"
          inputMode="email"
          required
          aria-invalid={error !== null ? 'true' : 'false'}
          placeholder={i18n.placeholder}
          value={email}
          onInput={(e) => setEmail((e.target as HTMLInputElement).value)}
        />
      </div>

      <div class="moonfarmer-reactions-lead-capture-field moonfarmer-reactions-lead-capture-consent-field">
        <input
          ref={consentRef}
          id={CONSENT_INPUT_ID}
          class="moonfarmer-reactions-lead-capture-checkbox"
          type="checkbox"
          required
          aria-describedby={CONSENT_HELP_ID}
          checked={consent}
          onChange={(e) => setConsent((e.target as HTMLInputElement).checked)}
        />
        <label class="moonfarmer-reactions-lead-capture-consent-label" for={CONSENT_INPUT_ID}>{i18n.consent}</label>
        <p id={CONSENT_HELP_ID} class="moonfarmer-reactions-lead-capture-consent-help">{i18n.consentHelper}</p>
      </div>

      {error !== null && (
        <p class="moonfarmer-reactions-lead-capture-error" role="alert">{error}</p>
      )}

      <div class="moonfarmer-reactions-lead-capture-capture-actions">
        <button
          type="submit"
          class="moonfarmer-reactions-lead-capture-submit"
          disabled={submitting}
          aria-busy={submitting ? 'true' : 'false'}
        >
          {submitting ? i18n.submitting : i18n.submit}
        </button>
        <button
          type="button"
          class="moonfarmer-reactions-lead-capture-link"
          onClick={onDismiss}
        >
          {i18n.dismiss}
        </button>
      </div>
    </form>
  );
}
