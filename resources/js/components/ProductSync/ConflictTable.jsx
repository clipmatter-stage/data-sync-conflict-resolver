import { Card, IndexTable, Text, Link, EmptyState, Badge, BlockStack } from '@shopify/polaris';
import { router } from '@inertiajs/react';
import StatusBadge from './StatusBadge';
import { withShopParam } from '@/utils/navigation';

export default function ConflictTable({ conflicts, emptyMessage }) {
  const resourceName = {
    singular: 'conflict',
    plural: 'conflicts',
  };

  const rowMarkup = conflicts.map(
    (conflict, index) => (
      <IndexTable.Row id={conflict.id} key={conflict.id} position={index}>
        <IndexTable.Cell>
          <BlockStack gap="100">
            <Text variant="bodyMd" fontWeight="semibold" as="span">
              {conflict.product.title}
            </Text>
            <Text variant="bodySm" tone="subdued" as="span">
              SKU: {conflict.product.sku || 'N/A'}
            </Text>
          </BlockStack>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Badge>{conflict.field_name}</Badge>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Text variant="bodySm" as="span">
            {conflict.ps_value || 'N/A'}
          </Text>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Text variant="bodySm" as="span">
            {conflict.shopify_value || 'N/A'}
          </Text>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <StatusBadge status={conflict.status} />
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Text variant="bodySm" tone="subdued" as="span">
            {new Date(conflict.detected_at).toLocaleDateString()}
          </Text>
        </IndexTable.Cell>
        <IndexTable.Cell>
          <Link
            onClick={() => router.visit(withShopParam(route('product-sync.conflicts.show', conflict.id)))}
          >
            View Details
          </Link>
        </IndexTable.Cell>
      </IndexTable.Row>
    ),
  );

  return (
    <Card padding="0">
      <IndexTable
        resourceName={resourceName}
        itemCount={conflicts.length}
        headings={[
          { title: 'Product' },
          { title: 'Field' },
          { title: 'Akeneo Value' },
          { title: 'Shopify Value' },
          { title: 'Status' },
          { title: 'Detected' },
          { title: 'Actions' },
        ]}
        selectable={false}
      >
        {conflicts.length > 0 ? rowMarkup : (
          <IndexTable.Row>
            <IndexTable.Cell colSpan={7}>
              <EmptyState
                heading={emptyMessage || 'No conflicts found'}
                image="https://cdn.shopify.com/s/files/1/0262/4071/2726/files/emptystate-files.png"
              >
                <p>All products are in sync!</p>
              </EmptyState>
            </IndexTable.Cell>
          </IndexTable.Row>
        )}
      </IndexTable>
    </Card>
  );
}
