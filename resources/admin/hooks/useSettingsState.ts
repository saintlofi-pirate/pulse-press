import { useCallback, useEffect, useRef, useState } from 'preact/hooks';
import { AdminRestError, saveSettings } from '../api';
import type { PulsePressAdminData, SettingsState } from '../types';

const PILL_VISIBLE_MS = 1500;
const ERROR_VISIBLE_MS = 4000;

type FieldKey = keyof SettingsState;

type Status = 'idle' | 'saving' | 'saved' | 'error';

export interface UseSettingsState {
  settings: SettingsState;
  defaults: SettingsState;
  choices: PulsePressAdminData['choices'];
  reactions: PulsePressAdminData['reactions'];
  version: string;
  fieldStatus: Partial<Record<FieldKey, Status>>;
  errors: Partial<Record<FieldKey, string>>;
  update: <K extends FieldKey>(key: K, value: SettingsState[K]) => Promise<void>;
  updateMany: (partial: Partial<SettingsState>) => Promise<void>;
  resetFields: (keys: FieldKey[]) => Promise<void>;
}

export function useSettingsState(data: PulsePressAdminData): UseSettingsState {
  const [settings, setSettings] = useState<SettingsState>(data.settings);
  const [fieldStatus, setFieldStatus] = useState<Partial<Record<FieldKey, Status>>>({});
  const [errors, setErrors] = useState<Partial<Record<FieldKey, string>>>({});
  const timers = useRef<Map<FieldKey, number>>(new Map());
  const settingsRef = useRef<SettingsState>(data.settings);
  const fieldVersions = useRef<Partial<Record<FieldKey, number>>>({});
  const versionCounter = useRef(0);

  const applySettings = useCallback((updater: (current: SettingsState) => SettingsState) => {
    setSettings((current) => {
      const next = updater(current);
      settingsRef.current = next;
      return next;
    });
  }, []);

  const flashStatus = useCallback((key: FieldKey, status: Status, autoclearMs?: number) => {
    setFieldStatus((prev) => ({ ...prev, [key]: status }));
    const existing = timers.current.get(key);
    if (existing !== undefined) {
      window.clearTimeout(existing);
      timers.current.delete(key);
    }
    if (autoclearMs !== undefined) {
      const handle = window.setTimeout(() => {
        setFieldStatus((prev) => {
          const next = { ...prev };
          delete next[key];
          return next;
        });
        timers.current.delete(key);
      }, autoclearMs);
      timers.current.set(key, handle);
    }
  }, []);

  useEffect(() => {
    return () => {
      timers.current.forEach((handle) => window.clearTimeout(handle));
      timers.current.clear();
    };
  }, []);

  const persist = useCallback(
    async (partial: Partial<SettingsState>) => {
      const keys = Object.keys(partial) as FieldKey[];
      const previousSnapshot = settingsRef.current;
      const requestVersions: Partial<Record<FieldKey, number>> = {};

      keys.forEach((key) => {
        const version = versionCounter.current + 1;
        versionCounter.current = version;
        fieldVersions.current[key] = version;
        requestVersions[key] = version;
      });

      applySettings((prev) => ({ ...prev, ...partial }));
      keys.forEach((k) => flashStatus(k, 'saving'));
      setErrors((prev) => {
        const next = { ...prev };
        keys.forEach((k) => {
          delete next[k];
        });
        return next;
      });

      try {
        const response = await saveSettings(data.restRoot, data.nonce, partial);
        const latestKeys = keys.filter((key) => fieldVersions.current[key] === requestVersions[key]);
        if (latestKeys.length > 0) {
          applySettings((current) => {
            const next = { ...current };
            latestKeys.forEach((key) => {
              next[key] = response.settings[key];
            });
            return next;
          });
          latestKeys.forEach((k) => flashStatus(k, 'saved', PILL_VISIBLE_MS));
        }
      } catch (err) {
        const message = err instanceof AdminRestError ? err.message : data.i18n.saveError;
        const latestKeys = keys.filter((key) => fieldVersions.current[key] === requestVersions[key]);
        if (latestKeys.length > 0) {
          applySettings((current) => {
            const next = { ...current };
            latestKeys.forEach((key) => {
              next[key] = previousSnapshot[key];
            });
            return next;
          });
        }
        latestKeys.forEach((k) => {
          flashStatus(k, 'error', ERROR_VISIBLE_MS);
          setErrors((prev) => ({ ...prev, [k]: message }));
        });
      }
    },
    [applySettings, data.i18n.saveError, data.nonce, data.restRoot, flashStatus]
  );

  const update = useCallback(
    async <K extends FieldKey>(key: K, value: SettingsState[K]) => {
      await persist({ [key]: value } as Partial<SettingsState>);
    },
    [persist]
  );

  const updateMany = useCallback(
    async (partial: Partial<SettingsState>) => {
      await persist(partial);
    },
    [persist]
  );

  const resetFields = useCallback(
    async (keys: FieldKey[]) => {
      const partial: Partial<SettingsState> = {};
      keys.forEach((key) => {
        (partial as Record<string, unknown>)[key] = data.defaults[key];
      });
      await persist(partial);
    },
    [data.defaults, persist]
  );

  return {
    settings,
    defaults: data.defaults,
    choices: data.choices,
    reactions: data.reactions,
    version: data.version,
    fieldStatus,
    errors,
    update,
    updateMany,
    resetFields,
  };
}
