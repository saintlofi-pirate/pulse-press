import { useCallback, useEffect, useState } from 'preact/hooks';
import type { ComponentType } from 'preact';
import { fetchAnalytics } from '../api';
import type { DailySeriesChart as DailySeriesChartComponent } from '../components/DailySeriesChart';
import { ExtensionMount } from '../components/ExtensionMount';
import { MetricCard } from '../components/MetricCard';
import type { TopPostsTable as TopPostsTableComponent } from '../components/TopPostsTable';
import type { MetricsEnvelope, MoonfarmerReactionsLeadCaptureAdminData } from '../types';

type ChartProps = Parameters<typeof DailySeriesChartComponent>[0];
type TableProps = Parameters<typeof TopPostsTableComponent>[0];

interface Props {
  adminData: MoonfarmerReactionsLeadCaptureAdminData;
}

type Status = 'loading' | 'ready' | 'error';

function formatPercent(value: number | null, fallback: string): string {
  if (value === null) return fallback;
  return `${Math.round(value * 100)}%`;
}

function formatInt(value: number): string {
  return value.toLocaleString();
}

function dominantReaction(series: MetricsEnvelope['dailySeries']): string | null {
  const totals: Record<string, number> = {};
  for (const day of Object.values(series)) {
    for (const [type, count] of Object.entries(day)) {
      totals[type] = (totals[type] ?? 0) + Number(count || 0);
    }
  }
  let best: { type: string; count: number } | null = null;
  for (const [type, count] of Object.entries(totals)) {
    if (best === null || count > best.count) {
      best = { type, count };
    }
  }
  return best?.type ?? null;
}

export function AnalyticsSection({ adminData }: Props) {
  const i18n = adminData.i18n.analytics;
  const [status, setStatus]   = useState<Status>('loading');
  const [data, setData]       = useState<MetricsEnvelope | null>(null);
  const [error, setError]     = useState<string | null>(null);
  const [LazyChart, setLazyChart] = useState<ComponentType<ChartProps> | null>(null);
  const [LazyTable, setLazyTable] = useState<ComponentType<TableProps> | null>(null);

  const load = useCallback(async () => {
    setStatus('loading');
    setError(null);
    try {
      const result = await fetchAnalytics(adminData.restRoot, adminData.nonce);
      setData(result);
      setStatus('ready');
    } catch (err) {
      const message = err instanceof Error ? err.message : i18n.errorState;
      setError(message);
      setStatus('error');
    }
  }, [adminData.nonce, adminData.restRoot, i18n.errorState]);

  useEffect(() => { void load(); }, [load]);

  useEffect(() => {
    let cancelled = false;
    void import('../components/DailySeriesChart').then((mod) => {
      if (!cancelled) setLazyChart(() => mod.DailySeriesChart);
    });
    void import('../components/TopPostsTable').then((mod) => {
      if (!cancelled) setLazyTable(() => mod.TopPostsTable);
    });
    return () => { cancelled = true; };
  }, []);

  if (status === 'loading') {
    return (
      <section class="moonfarmer-reactions-lead-capture-section" aria-busy="true">
        <header class="moonfarmer-reactions-lead-capture-section__header">
          <h2>{adminData.i18n.sections.analyticsTitle}</h2>
          <p class="moonfarmer-reactions-lead-capture-section__helper">{adminData.i18n.sections.analyticsHelper}</p>
        </header>
        <p class="moonfarmer-reactions-lead-capture-loading-skeleton">{i18n.loadingState}</p>
      </section>
    );
  }

  if (status === 'error' || data === null) {
    return (
      <section class="moonfarmer-reactions-lead-capture-section">
        <header class="moonfarmer-reactions-lead-capture-section__header">
          <h2>{adminData.i18n.sections.analyticsTitle}</h2>
        </header>
        <p role="alert" class="moonfarmer-reactions-lead-capture-error">{error ?? i18n.errorState}</p>
        <button type="button" class="moonfarmer-reactions-lead-capture-submit" onClick={() => void load()}>{i18n.retry}</button>
      </section>
    );
  }

  const isEmpty = data.totalReactions === 0 && data.totalCaptures === 0;
  const dominant = dominantReaction(data.dailySeries);
  const sentimentInsight = dominant && data.sentimentRate !== null
    ? i18n.sentimentInsightTemplate
        .replace('{type}', dominant.charAt(0).toUpperCase() + dominant.slice(1))
        .replace('{percent}', String(Math.round(data.sentimentRate * 100)))
    : i18n.sentimentInsightFallback;

  return (
    <section class="moonfarmer-reactions-lead-capture-section" aria-labelledby="moonfarmer-reactions-lead-capture-section-analytics-title">
      <header class="moonfarmer-reactions-lead-capture-section__header">
        <h2 id="moonfarmer-reactions-lead-capture-section-analytics-title">{adminData.i18n.sections.analyticsTitle}</h2>
        <p class="moonfarmer-reactions-lead-capture-section__helper">
          {adminData.i18n.sections.analyticsHelper}
          {data.clamped && (
            <>
              {' '}
              <span class="moonfarmer-reactions-lead-capture-clamped-notice">{i18n.clampedNotice}</span>
            </>
          )}
        </p>
      </header>

      {isEmpty ? (
        <p class="moonfarmer-reactions-lead-capture-empty-state">{i18n.emptyState}</p>
      ) : (
        <div class="moonfarmer-reactions-lead-capture-section__body">
          <div class="moonfarmer-reactions-lead-capture-metric-grid">
            <MetricCard title={i18n.totalReactionsLabel} value={formatInt(data.totalReactions)} helper={i18n.totalReactionsHelper} emphasis />
            <MetricCard title={i18n.totalCapturesLabel}  value={formatInt(data.totalCaptures)}  helper={i18n.totalCapturesHelper} />
            <MetricCard title={i18n.sentimentRateLabel}  value={formatPercent(data.sentimentRate, '—')} helper={i18n.sentimentRateHelper} />
            <MetricCard title={i18n.captureRateLabel}    value={formatPercent(data.captureRate, '—')}   helper={i18n.captureRateHelper} />
            {adminData.metricCards.map((card) => (
              card.renderJs
                ? <ExtensionMount key={`card-${card.id}`} kind="card" id={card.id} adminData={adminData} data={card.data ?? card} fallback={card.fallback} ariaLabel={card.title} />
                : <MetricCard key={`card-${card.id}`} title={card.title} value={card.value} helper={card.helper} emphasis={card.emphasis} />
            ))}
          </div>

          <p class="moonfarmer-reactions-lead-capture-insight" role="status">{sentimentInsight}</p>

          {LazyChart !== null
            ? <LazyChart series={data.dailySeries} from={data.from} to={data.to} i18n={i18n} />
            : <div class="moonfarmer-reactions-lead-capture-skeleton moonfarmer-reactions-lead-capture-skeleton--chart" role="status" aria-live="polite" aria-label={i18n.loadingState} />}

          {LazyTable !== null
            ? <LazyTable rows={data.topPosts} titles={data.postTitles} i18n={i18n} />
            : <div class="moonfarmer-reactions-lead-capture-skeleton moonfarmer-reactions-lead-capture-skeleton--table" role="status" aria-live="polite" aria-label={i18n.loadingState} />}

          {adminData.analyticsPanels.map((panel) => (
            <ExtensionMount
              key={`panel-${panel.id}`}
              kind="panel"
              id={panel.id}
              adminData={adminData}
              data={panel.data ?? panel}
              fallback={panel.fallback}
              ariaLabel={panel.title}
            />
          ))}
        </div>
      )}
    </section>
  );
}
