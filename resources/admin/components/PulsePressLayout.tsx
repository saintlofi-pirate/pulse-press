import type { ComponentChildren } from 'preact';
import type { PulsePressAdminData } from '../types';

interface Props {
  adminData: PulsePressAdminData;
  nav: ComponentChildren;
  panel: ComponentChildren;
  preview: ComponentChildren;
}

export function PulsePressLayout({ adminData, nav, panel, preview }: Props) {
  return (
    <div class="pulsepress-admin">
      <header class="pulsepress-admin__header">
        <div class="pulsepress-admin__brand">
          <span class="pulsepress-admin__wordmark">PulsePress</span>
          <span class="pulsepress-admin__version" aria-label={`Version ${adminData.version}`}>v{adminData.version}</span>
        </div>
        <p class="pulsepress-admin__tagline">{adminData.i18n.pageTitle}</p>
      </header>
      <div class="pulsepress-admin__nav">{nav}</div>
      <main class="pulsepress-admin__layout">
        <div class="pulsepress-admin__main">{panel}</div>
        <div class="pulsepress-admin__preview">{preview}</div>
      </main>
    </div>
  );
}
