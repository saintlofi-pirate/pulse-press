import type { MetricsEnvelope, SettingsChoices, SettingsState } from './types';

export interface SettingsResponse {
  settings: SettingsState;
  defaults: SettingsState;
  choices: SettingsChoices;
  schema_version?: number;
}

export class AdminRestError extends Error {
  constructor(public code: string, message: string, public status: number) {
    super(message);
  }
}

function rootFor(restRoot: string): string {
  return restRoot.endsWith('/') ? restRoot : `${restRoot}/`;
}

async function parseJson<T>(response: Response): Promise<T> {
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    const code = typeof body === 'object' && body && 'code' in body ? String((body as { code: unknown }).code) : 'pulsepress_request_failed';
    const message = typeof body === 'object' && body && 'message' in body ? String((body as { message: unknown }).message) : response.statusText;
    throw new AdminRestError(code, message, response.status);
  }
  return body as T;
}

export async function fetchSettings(restRoot: string, nonce: string): Promise<SettingsResponse> {
  const response = await fetch(`${rootFor(restRoot)}settings`, {
    headers: { Accept: 'application/json', 'X-WP-Nonce': nonce },
    credentials: 'same-origin',
  });
  return parseJson<SettingsResponse>(response);
}

export async function saveSettings(
  restRoot: string,
  nonce: string,
  partial: Partial<SettingsState>
): Promise<SettingsResponse> {
  const response = await fetch(`${rootFor(restRoot)}settings`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'same-origin',
    body: JSON.stringify(partial),
  });
  return parseJson<SettingsResponse>(response);
}

export async function fetchAnalytics(restRoot: string, nonce: string): Promise<MetricsEnvelope> {
  const response = await fetch(`${rootFor(restRoot)}analytics/summary`, {
    headers: { Accept: 'application/json', 'X-WP-Nonce': nonce },
    credentials: 'same-origin',
  });
  return parseJson<MetricsEnvelope>(response);
}
