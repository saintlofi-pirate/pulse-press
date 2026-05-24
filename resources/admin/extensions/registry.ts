import { useEffect, useState } from 'preact/hooks';
import type { ExtensionKind, ExtensionRenderer } from '../types';

const EVENT_NAME = 'moonfarmer-reactions-lead-capture:extension-registered';

class ExtensionRegistry {
  private readonly tabs = new Map<string, ExtensionRenderer>();
  private readonly cards = new Map<string, ExtensionRenderer>();
  private readonly panels = new Map<string, ExtensionRenderer>();

  registerTabRenderer = (id: string, renderer: ExtensionRenderer): void => {
    this.tabs.set(id, renderer);
    this.notify();
  };

  registerCardRenderer = (id: string, renderer: ExtensionRenderer): void => {
    this.cards.set(id, renderer);
    this.notify();
  };

  registerPanelRenderer = (id: string, renderer: ExtensionRenderer): void => {
    this.panels.set(id, renderer);
    this.notify();
  };

  getRenderer(kind: ExtensionKind, id: string): ExtensionRenderer | undefined {
    if (kind === 'tab') return this.tabs.get(id);
    if (kind === 'card') return this.cards.get(id);
    return this.panels.get(id);
  }

  private notify(): void {
    if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') return;
    window.dispatchEvent(new CustomEvent(EVENT_NAME));
  }
}

let singleton: ExtensionRegistry | null = null;

export function getRegistry(): ExtensionRegistry {
  if (singleton === null) {
    singleton = new ExtensionRegistry();
  }
  return singleton;
}

export function useExtensionTick(): number {
  const [tick, setTick] = useState(0);
  useEffect(() => {
    const handler = () => setTick((t) => t + 1);
    window.addEventListener(EVENT_NAME, handler);
    return () => window.removeEventListener(EVENT_NAME, handler);
  }, []);
  return tick;
}

export const EXTENSION_EVENT = EVENT_NAME;
