import { h, render } from 'preact';
import { App } from './App';
import { getRegistry } from './extensions/registry';
import './styles/admin.css';
import type { PulsePressAdminData } from './types';

function exposeExtensionApi() {
  if (typeof window === 'undefined') return;
  if (window.PulsePressAdmin) return;
  const registry = getRegistry();
  window.PulsePressAdmin = {
    registerTabRenderer: registry.registerTabRenderer,
    registerCardRenderer: registry.registerCardRenderer,
    registerPanelRenderer: registry.registerPanelRenderer,
  };
}

function mount(data: PulsePressAdminData) {
  const target = document.getElementById('pulsepress-admin');
  if (!target) return;
  target.innerHTML = '';
  render(h(App, { adminData: data }), target);
}

function boot() {
  exposeExtensionApi();
  const data = window.PulsePressAdminData;
  if (!data) return;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mount(data), { once: true });
  } else {
    mount(data);
  }
}

boot();
