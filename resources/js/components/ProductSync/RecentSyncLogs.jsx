import { Card, DataTable, EmptyState } from '@shopify/polaris';
import StatusBadge from './StatusBadge';

export default function RecentSyncLogs({ logs }) {
  if (!logs || logs.length === 0) {
    return (
      <Card>
        <EmptyState
          heading="No sync logs yet"
          image="https://cdn.shopify.com/s/files/1/0262/4071/2726/files/emptystate-files.png"
        >
          <p>Sync logs will appear here after you run a product sync.</p>
        </EmptyState>
      </Card>
    );
  }

  const rows = logs.map((log) => [
    new Date(log.created_at).toLocaleString(),
    log.action.replace(/_/g, ' ').toUpperCase(),
    <StatusBadge status={log.status} />,
    log.message || '-',
  ]);

  return (
    <Card>
      <DataTable
        columnContentTypes={['text', 'text', 'text', 'text']}
        headings={['Time', 'Action', 'Status', 'Message']}
        rows={rows}
      />
    </Card>
  );
}
