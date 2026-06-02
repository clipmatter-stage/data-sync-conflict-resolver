import { InlineGrid, Box, Card, Text, BlockStack } from '@shopify/polaris';

export default function DifferenceViewer({ label1, data1, label2, data2, highlightField }) {
  const fields = [
    'title',
    'sku',
    'description',
    'vendor',
    'product_type',
    'price',
    'compare_at_price',
    'inventory_quantity',
    'status',
    'tags',
  ];

  const renderValue = (value) => {
    if (value === null || value === undefined) return 'N/A';
    if (Array.isArray(value)) return value.join(', ');
    return String(value);
  };

  const isHighlighted = (field) => field === highlightField;

  return (
    <InlineGrid columns={2} gap="400">
      <Card>
        <BlockStack gap="300">
          <Text variant="headingMd" as="h3">
            {label1}
          </Text>
          {fields.map((field) => (
            <BlockStack gap="100" key={field}>
              <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                {field.replace(/_/g, ' ').toUpperCase()}
              </Text>
              <Box
                padding="200"
                background={isHighlighted(field) ? 'bg-fill-warning' : 'bg-fill'}
                borderRadius="100"
              >
                <Text variant="bodySm">{renderValue(data1?.[field])}</Text>
              </Box>
            </BlockStack>
          ))}
        </BlockStack>
      </Card>

      <Card>
        <BlockStack gap="300">
          <Text variant="headingMd" as="h3">
            {label2}
          </Text>
          {fields.map((field) => (
            <BlockStack gap="100" key={field}>
              <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                {field.replace(/_/g, ' ').toUpperCase()}
              </Text>
              <Box
                padding="200"
                background={isHighlighted(field) ? 'bg-fill-warning' : 'bg-fill'}
                borderRadius="100"
              >
                <Text variant="bodySm">{renderValue(data2?.[field])}</Text>
              </Box>
            </BlockStack>
          ))}
        </BlockStack>
      </Card>
    </InlineGrid>
  );
}
