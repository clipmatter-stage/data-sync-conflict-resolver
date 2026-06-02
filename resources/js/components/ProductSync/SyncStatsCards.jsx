import { Card, BlockStack, InlineGrid, Text, Box } from '@shopify/polaris';

export default function SyncStatsCards({ stats }) {
  const statCards = [
    {
      label: 'Total Products',
      value: stats.total_products || 0,
      helpText: 'Products in local database',
    },
    {
      label: 'Shopify Products',
      value: stats.shopify_products || 0,
      helpText: 'Products synced to Shopify',
    },
    {
      label: 'Pending Conflicts',
      value: stats.pending_conflicts || 0,
      helpText: 'Conflicts awaiting resolution',
      tone: stats.pending_conflicts > 0 ? 'critical' : 'success',
    },
    {
      label: 'Resolved Conflicts',
      value: stats.resolved_conflicts || 0,
      helpText: 'Successfully resolved',
      tone: 'success',
    },
    {
      label: 'Ignored Conflicts',
      value: stats.ignored_conflicts || 0,
      helpText: 'Conflicts ignored by user',
    },
    {
      label: 'Failed Syncs',
      value: stats.failed_syncs || 0,
      helpText: 'Sync attempts that failed',
      tone: stats.failed_syncs > 0 ? 'critical' : 'success',
    },
  ];

  return (
    <InlineGrid columns={{ xs: 1, sm: 2, md: 3, lg: 6 }} gap="400">
      {statCards.map((stat, index) => (
        <Card key={index}>
          <BlockStack gap="200">
            <Text variant="headingSm" as="h3" tone="subdued">
              {stat.label}
            </Text>
            <Text variant="heading2xl" as="p" tone={stat.tone}>
              {stat.value}
            </Text>
            <Text variant="bodySm" tone="subdued">
              {stat.helpText}
            </Text>
          </BlockStack>
        </Card>
      ))}
    </InlineGrid>
  );
}
