import {
  Page,
  Layout,
  Card,
  BlockStack,
  Text,
  Badge,
  InlineGrid,
  Box,
  Divider,
  Banner,
} from '@shopify/polaris';
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { TitleBar } from '@shopify/app-bridge-react';
import StatusBadge from '@/components/ProductSync/StatusBadge';
import DifferenceViewer from '@/components/ProductSync/DifferenceViewer';
import ResolveConflictModal from '@/components/ProductSync/ResolveConflictModal';
import PageFeedback from '@/components/ProductSync/PageFeedback';
import SectionHeader from '@/components/ProductSync/SectionHeader';
import { withShopParam } from '@/utils/navigation';

export default function ConflictShow({ conflict }) {
  const { flash = {}, errors = {} } = usePage().props;
  const [showResolveModal, setShowResolveModal] = useState(false);
  const [ignoring, setIgnoring] = useState(false);
  const [actionError, setActionError] = useState(null);

  const handleIgnore = () => {
    setIgnoring(true);
    setActionError(null);

    router.post(
      withShopParam(route('product-sync.conflicts.ignore', conflict.id)),
      {},
      {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
          setIgnoring(false);
        },
        onError: (inertiaErrors) => {
          const firstError = Object.values(inertiaErrors || {})[0];
          setActionError(firstError || 'Failed to ignore conflict. Please try again.');
        },
      }
    );
  };

  const handleResolveSuccess = () => {
    router.reload({ preserveScroll: true });
  };

  const handleResolveError = (message) => {
    setActionError(message);
  };

  const isPending = conflict.status === 'pending';

  return (
    <>
      <TitleBar title={`Conflict: ${conflict.product.title}`}>
        {isPending && (
          <>
            <button variant="secondary" onClick={handleIgnore} disabled={ignoring}>
              {ignoring ? 'Ignoring…' : 'Ignore'}
            </button>
            <button variant="primary" onClick={() => setShowResolveModal(true)}>
              Resolve Conflict
            </button>
          </>
        )}
      </TitleBar>
      <Page>
        <Layout>
        <Layout.Section>
          <BlockStack gap="500">
            <PageFeedback flash={flash} errors={errors} error={actionError} />

            {!isPending && (
              <Banner
                tone={conflict.status === 'resolved' ? 'success' : 'info'}
                title={`This conflict has been ${conflict.status}`}
              >
                {conflict.status === 'resolved' && (
                  <p>
                    Resolved using: <strong>{conflict.resolution_source}</strong> on{' '}
                    {new Date(conflict.resolved_at).toLocaleString()}
                  </p>
                )}
              </Banner>
            )}

            <Card>
              <BlockStack gap="400">
                <SectionHeader title="Product Information" />

                <InlineGrid columns={2} gap="400">
                  <BlockStack gap="200">
                    <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                      Product Title
                    </Text>
                    <Text variant="bodyMd">{conflict.product.title}</Text>
                  </BlockStack>

                  <BlockStack gap="200">
                    <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                      SKU
                    </Text>
                    <Text variant="bodyMd">{conflict.product.sku || 'N/A'}</Text>
                  </BlockStack>
                </InlineGrid>

                <Divider />

                <InlineGrid columns={3} gap="400">
                  <BlockStack gap="200">
                    <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                      Conflict Field
                    </Text>
                    <Badge>{conflict.field_name}</Badge>
                  </BlockStack>

                  <BlockStack gap="200">
                    <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                      Status
                    </Text>
                    <StatusBadge status={conflict.status} />
                  </BlockStack>

                  <BlockStack gap="200">
                    <Text variant="bodyMd" fontWeight="semibold" tone="subdued">
                      Detected At
                    </Text>
                    <Text variant="bodyMd">
                      {new Date(conflict.detected_at).toLocaleString()}
                    </Text>
                  </BlockStack>
                </InlineGrid>
              </BlockStack>
            </Card>

            <Card>
              <BlockStack gap="400">
                <SectionHeader title="Conflicting Values" />

                <InlineGrid columns={2} gap="400">
                  <Card>
                    <BlockStack gap="200">
                      <Text variant="headingSm" fontWeight="semibold">
                        Akeneo Value
                      </Text>
                      <Box padding="300" background="bg-fill-warning" borderRadius="200">
                        <Text variant="bodyMd">{conflict.ps_value || 'N/A'}</Text>
                      </Box>
                    </BlockStack>
                  </Card>

                  <Card>
                    <BlockStack gap="200">
                      <Text variant="headingSm" fontWeight="semibold">
                        Shopify Value
                      </Text>
                      <Box padding="300" background="bg-fill-info" borderRadius="200">
                        <Text variant="bodyMd">{conflict.shopify_value || 'N/A'}</Text>
                      </Box>
                    </BlockStack>
                  </Card>
                </InlineGrid>

                {conflict.resolved_value && (
                  <Card>
                    <BlockStack gap="200">
                      <Text variant="headingSm" fontWeight="semibold">
                        Resolved Value
                      </Text>
                      <Box padding="300" background="bg-fill-success" borderRadius="200">
                        <Text variant="bodyMd">{conflict.resolved_value}</Text>
                      </Box>
                    </BlockStack>
                  </Card>
                )}
              </BlockStack>
            </Card>

            <Card>
              <BlockStack gap="400">
                <SectionHeader title="Complete Product Comparison" />
                <Text variant="bodySm" tone="subdued">
                  Compare all fields between Akeneo and Shopify. Highlighted fields indicate the conflict.
                </Text>
                <DifferenceViewer
                  label1="Akeneo Data"
                  data1={conflict.ps_payload}
                  label2="Shopify Data"
                  data2={conflict.shopify_payload}
                  highlightField={conflict.field_name}
                />
              </BlockStack>
            </Card>
          </BlockStack>
        </Layout.Section>
      </Layout>
    </Page>

    <ResolveConflictModal
      active={showResolveModal}
      onClose={() => setShowResolveModal(false)}
      conflict={conflict}
      onSuccess={handleResolveSuccess}
      onError={handleResolveError}
    />
    </>
  );
}
