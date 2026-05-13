import { h, render } from 'preact';
import { App } from './App';
import './styles/admin.css';
import type { PulsePressAdminData } from './types';

function mount(data: PulsePressAdminData) {
  const target = document.getElementById('pulsepress-admin');
  if (!target) return;
  target.innerHTML = '';
  render(h(App, { adminData: data }), target);
}

function boot() {
  const data = window.PulsePressAdminData;
  if (!data) return;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mount(data), { once: true });
  } else {
    mount(data);
  }
}

boot();
