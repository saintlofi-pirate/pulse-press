import type { MetricsEnvelope, MoonfarmerReactionsLeadCaptureAdminData } from '../types';

interface Props {
  rows: MetricsEnvelope['topPosts'];
  titles: MetricsEnvelope['postTitles'];
  i18n: MoonfarmerReactionsLeadCaptureAdminData['i18n']['analytics'];
}

export function TopPostsTable({ rows, titles, i18n }: Props) {
  if (rows.length === 0) {
    return <p class="moonfarmer-reactions-lead-capture-empty-state">{i18n.emptyState}</p>;
  }

  return (
    <table class="moonfarmer-reactions-lead-capture-top-posts">
      <caption class="moonfarmer-reactions-lead-capture-sr-only">{i18n.topPostsCaption}</caption>
      <thead>
        <tr>
          <th scope="col">{i18n.topPostsColumns.post}</th>
          <th scope="col">{i18n.topPostsColumns.total}</th>
          <th scope="col">{i18n.topPostsColumns.positive}</th>
          <th scope="col">{i18n.topPostsColumns.captures}</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => {
          const title = titles[String(row.post_id)] ?? i18n.deletedPost;
          return (
            <tr key={row.post_id}>
              <th scope="row">{title}</th>
              <td>{row.total.toLocaleString()}</td>
              <td>{row.positive.toLocaleString()}</td>
              <td>{row.captures.toLocaleString()}</td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}
