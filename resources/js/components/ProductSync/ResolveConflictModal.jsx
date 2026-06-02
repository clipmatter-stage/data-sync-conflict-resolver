import { Modal, Form, FormLayout, ChoiceList, TextField, TextContainer, Text, BlockStack, Banner } from '@shopify/polaris';
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { withShopParam } from '@/utils/navigation';

export default function ResolveConflictModal({ active, onClose, conflict, onSuccess, onError }) {
  const [resolutionSource, setResolutionSource] = useState(['ps_restful']);
  const [customValue, setCustomValue] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = () => {
    setLoading(true);

    const source = resolutionSource[0];
    const data = {
      resolution_source: source,
    };

    if (source === 'custom') {
      data.custom_value = customValue;
    }

    router.post(
      withShopParam(route('product-sync.conflicts.resolve', conflict.id)),
      data,
      {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
          setLoading(false);
        },
        onSuccess: () => {
          onClose();
          if (onSuccess) onSuccess();
        },
        onError: (errors) => {
          const firstError = Object.values(errors || {})[0];
          if (typeof onError === 'function') {
            onError(firstError || 'Failed to resolve conflict. Please try again.');
          }
        },
      }
    );
  };

  if (!conflict) return null;

  return (
    <Modal
      open={active}
      onClose={onClose}
      title="Resolve Product Conflict"
      primaryAction={{
        content: 'Resolve Conflict',
        loading: loading,
        onAction: handleSubmit,
        disabled: resolutionSource[0] === 'custom' && !customValue,
      }}
      secondaryActions={[
        {
          content: 'Cancel',
          onAction: onClose,
        },
      ]}
    >
      <Modal.Section>
        <BlockStack gap="400">
          <Banner>
            <p>
              Choose which value should be used to resolve this conflict. Shopify will be updated if required.
            </p>
          </Banner>

          <TextContainer>
            <Text variant="headingMd" as="h3">
              {conflict.product.title}
            </Text>
            <Text variant="bodySm" tone="subdued">
              SKU: {conflict.product.sku || 'N/A'}
            </Text>
          </TextContainer>

          <BlockStack gap="200">
            <Text variant="bodyMd" fontWeight="semibold">
              Conflict Field
            </Text>
            <Text variant="bodySm">{conflict.field_name}</Text>
          </BlockStack>

          <BlockStack gap="200">
            <Text variant="bodyMd" fontWeight="semibold">
              Akeneo Value
            </Text>
            <Text variant="bodySm">{conflict.ps_value || 'N/A'}</Text>
          </BlockStack>

          <BlockStack gap="200">
            <Text variant="bodyMd" fontWeight="semibold">
              Shopify Value
            </Text>
            <Text variant="bodySm">{conflict.shopify_value || 'N/A'}</Text>
          </BlockStack>

          <Form onSubmit={handleSubmit}>
            <FormLayout>
              <ChoiceList
                title="Resolution Option"
                choices={[
                  {
                    label: 'Use Akeneo Value',
                    value: 'ps_restful',
                    helpText: 'Update Shopify with Akeneo value',
                  },
                  {
                    label: 'Use Shopify Value',
                    value: 'shopify',
                    helpText: 'Keep current Shopify value',
                  },
                  {
                    label: 'Enter Custom Value',
                    value: 'custom',
                    helpText: 'Provide a custom value',
                  },
                  {
                    label: 'Ignore Conflict',
                    value: 'ignored',
                    helpText: 'Mark as ignored without updating Shopify',
                  },
                ]}
                selected={resolutionSource}
                onChange={setResolutionSource}
              />

              {resolutionSource[0] === 'custom' && (
                <TextField
                  label="Custom Value"
                  value={customValue}
                  onChange={setCustomValue}
                  placeholder="Enter custom value"
                  autoComplete="off"
                  requiredIndicator
                />
              )}
            </FormLayout>
          </Form>
        </BlockStack>
      </Modal.Section>
    </Modal>
  );
}
