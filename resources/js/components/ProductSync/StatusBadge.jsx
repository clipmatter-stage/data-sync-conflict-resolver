import { Badge } from '@shopify/polaris';

const statusConfig = {
  pending: { status: 'warning', text: 'Pending' },
  resolved: { status: 'success', text: 'Resolved' },
  ignored: { status: 'info', text: 'Ignored' },
  failed: { status: 'critical', text: 'Failed' },
  success: { status: 'success', text: 'Success' },
  warning: { status: 'warning', text: 'Warning' },
  info: { status: 'info', text: 'Info' },
};

export default function StatusBadge({ status }) {
  const config = statusConfig[status] || { status: 'info', text: status };

  return <Badge tone={config.status}>{config.text}</Badge>;
}
