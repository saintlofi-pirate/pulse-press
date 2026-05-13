import type { PulsePressAdminData } from '../types';

interface Props {
  status?: 'saving' | 'saved' | 'error';
  i18n: PulsePressAdminData['i18n'];
}

export function StatusPill({ status, i18n }: Props) {
  if (!status) return null;
  if (status === 'saving') {
    return <span class="pulsepress-pill pulsepress-pill--saving" aria-hidden="true">{i18n.saving}</span>;
  }
  if (status === 'saved') {
    return <span class="pulsepress-pill pulsepress-pill--saved" role="status" aria-live="polite">{i18n.saved}</span>;
  }
  return null;
}
