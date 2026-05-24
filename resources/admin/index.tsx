import { h, render } from 'preact';
import { App } from './App';
import { getRegistry } from './extensions/registry';
import './styles/admin.css';
import type { MoonfarmerReactionsLeadCaptureAdminData } from './types';

function exposeExtensionApi() {
  if (typeof window === 'undefined') return;
  if (window.MoonfarmerReactionsLeadCaptureAdmin) return;
  const registry = getRegistry();
  window.MoonfarmerReactionsLeadCaptureAdmin = {
    registerTabRenderer: registry.registerTabRenderer,
    registerCardRenderer: registry.registerCardRenderer,
    registerPanelRenderer: registry.registerPanelRenderer,
  };
}

function mount(data: MoonfarmerReactionsLeadCaptureAdminData) {
  const target = document.getElementById('moonfarmer-reactions-lead-capture-admin');
  if (!target) return;
  target.innerHTML = '';
  render(h(App, { adminData: data }), target);
}

function boot() {
  exposeExtensionApi();
  const data = window.MoonfarmerReactionsLeadCaptureAdminData;
  if (!data) return;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mount(data), { once: true });
  } else {
    mount(data);
  }
}

boot();
