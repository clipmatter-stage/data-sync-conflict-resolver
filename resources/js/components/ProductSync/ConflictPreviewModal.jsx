import { Modal, TextContainer, InlineGrid, Card, Text, BlockStack, Box, Divider } from '@shopify/polaris';

export default function ConflictPreviewModal({ active, onClose, conflict }) {
  if (!conflict) return null;

  const fieldData = [
    { label: 'Title', psKey: 'title', shopifyKey: 'title' },
    { label: 'SKU', psKey: 'sku', shopifyKey: 'sku' },
    { label: 'Description', psKey: 'description', shopifyKey: 'description' },
    { label: 'Vendor', psKey: 'vendor', shopifyKey: 'vendor' },
    { label: 'Product Type', psKey: 'product_type', shopifyKey: 'product_type' },
    { label: 'Price', psKey: 'price', shopifyKey: 'price' },
    { label: 'Compare at Price', psKey: 'compare_at_price', shopifyKey: 'compare_at_price' },
    { label: 'Inventory', psKey: 'inventory_quantity', shopifyKey: 'inventory_quantity' },
    { label: 'Status', psKey: 'status', shopifyKey: 'status' },
    { label: 'Tags', psKey: 'tags', shopifyKey: 'tags' },
  ];

  const renderValue = (value) => {
    if (value === null || value === undefined) return 'N/A';
    if (Array.isArray(value)) return value.join(', ');
    return String(value);
  };

  return (
    <Modal
      large
      open={active}
      onClose={onClose}
      title="Product Comparison"
      primaryAction={{
        content: 'Close',
        onAction: onClose,
      }}
    >
      <Modal.Section>
        <BlockStack gap="400">
          <TextContainer>
            <Text variant="headingMd" as="h3">
              {conflict.product.title}
            </Text>
            <Text variant="bodySm" tone="subdued">
              SKU: {conflict.product.sku || 'N/A'}
            </Text>
          </TextContainer>

          <Divider />

          <InlineGrid columns={2} gap="400">
            <Card>
              <BlockStack gap="300">
                <Text variant="headingMd" as="h3">
                  Akeneo Data
                </Text>
                {fieldData.map((field) => (
                  <BlockStack gap="100" key={field.label}>
                    <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                      {field.label}
                    </Text>
                    <Box
                      padding="200"
                      background={
                        conflict.field_name === field.psKey ? 'bg-fill-warning' : 'bg-fill'
                      }
                      borderRadius="100"
                    >
                      <Text variant="bodySm">
                        {renderValue(conflict.ps_payload?.[field.psKey])}
                      </Text>
                    </Box>
                  </BlockStack>
                ))}
              </BlockStack>
            </Card>

            <Card>
              <BlockStack gap="300">
                <Text variant="headingMd" as="h3">
                  Shopify Data
                </Text>
                {fieldData.map((field) => (
                  <BlockStack gap="100" key={field.label}>
                    <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                      {field.label}
                    </Text>
                    <Box
                      padding="200"
                      background={
                        conflict.field_name === field.shopifyKey ? 'bg-fill-warning' : 'bg-fill'
                      }
                      borderRadius="100"
                    >
                      <Text variant="bodySm">
                        {renderValue(conflict.shopify_payload?.[field.shopifyKey])}
                      </Text>
                    </Box>
                  </BlockStack>
                ))}
              </BlockStack>
            </Card>
          </InlineGrid>
        </BlockStack>
      </Modal.Section>
    </Modal>
  );
}
