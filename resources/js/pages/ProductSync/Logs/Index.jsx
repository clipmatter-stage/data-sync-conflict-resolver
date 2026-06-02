import {
  Page,
  Layout,
  Card,
  Box,
  Filters,
  ChoiceList,
  IndexTable,
  Text,
  EmptyState,
  Pagination,
  BlockStack,
  Badge,
  InlineStack,
} from '@shopify/polaris';
import { useState, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';
import { TitleBar } from '@shopify/app-bridge-react';
import StatusBadge from '@/components/ProductSync/StatusBadge';
import PageFeedback from '@/components/ProductSync/PageFeedback';
import { withShopParams } from '@/utils/navigation';

export default function LogsIndex({ logs, filters }) {
  const { flash = {}, errors = {} } = usePage().props;
  const [action, setAction] = useState(filters.action ? [filters.action] : []);
  const [status, setStatus] = useState(filters.status ? [filters.status] : []);

  const handleActionChange = useCallback((value) => setAction(value), []);
  const handleStatusChange = useCallback((value) => setStatus(value), []);

  const handleActionRemove = useCallback(() => {
    setAction([]);
    applyFilters({ action: '' });
  }, []);

  const handleStatusRemove = useCallback(() => {
    setStatus([]);
    applyFilters({ status: '' });
  }, []);

  const handleClearAll = useCallback(() => {
    setAction([]);
    setStatus([]);
    router.get(route('product-sync.logs.index'), withShopParams());
  }, []);

  const applyFilters = (newFilters) => {
    const params = {
      action: action[0] || newFilters.action || '',
      status: status[0] || newFilters.status || '',
    };

    // Remove empty values
    Object.keys(params).forEach((key) => {
      if (!params[key]) delete params[key];
    });

    router.get(route('product-sync.logs.index'), withShopParams(params), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const filtersMarkup = (
    <Filters
      queryValue=""
      filters={[
        {
          key: 'action',
          label: 'Action',
          filter: (
            <ChoiceList
              title="Action"
              titleHidden
              choices={[
                { label: 'Sync Started', value: 'sync_started' },
                { label: 'Sync Completed', value: 'sync_completed' },
                { label: 'Product Created', value: 'product_created' },
                { label: 'Product Updated', value: 'product_updated' },
                { label: 'Conflict Detected', value: 'conflict_detected' },
                { label: 'Conflict Resolved', value: 'conflict_resolved' },
                { label: 'Conflict Ignored', value: 'conflict_ignored' },
                { label: 'Sync Failed', value: 'sync_failed' },
              ]}
              selected={action}
              onChange={handleActionChange}
            />
          ),
          shortcut: true,
        },
        {
          key: 'status',
          label: 'Status',
          filter: (
            <ChoiceList
              title="Status"
              titleHidden
              choices={[
                { label: 'Success', value: 'success' },
                { label: 'Failed', value: 'failed' },
                { label: 'Warning', value: 'warning' },
                { label: 'Info', value: 'info' },
              ]}
              selected={status}
              onChange={handleStatusChange}
            />
          ),
        },
      ]}
      appliedFilters={[
        ...(action.length > 0
          ? [
              {
                key: 'action',
                label: `Action: ${action[0].replace(/_/g, ' ')}`,
                onRemove: handleActionRemove,
              },
            ]
          : []),
        ...(status.length > 0
          ? [
              {
                key: 'status',
                label: `Status: ${status[0]}`,
                onRemove: handleStatusRemove,
              },
            ]
          : []),
      ]}
      onClearAll={handleClearAll}
      hideQueryField
    />
  );

  const resourceName = {
    singular: 'log',
    plural: 'logs',
  };

  const rowMarkup = logs.data.map((log, index) => (
    <IndexTable.Row id={log.id} key={log.id} position={index}>
      <IndexTable.Cell>
        <Text variant="bodySm" tone="subdued" as="span">
          {new Date(log.created_at).toLocaleString()}
        </Text>
      </IndexTable.Cell>
      <IndexTable.Cell>
        <Badge>{log.action.replace(/_/g, ' ').toUpperCase()}</Badge>
      </IndexTable.Cell>
      <IndexTable.Cell>
        <StatusBadge status={log.status} />
      </IndexTable.Cell>
      <IndexTable.Cell>
        <Text variant="bodySm" as="span">
          {log.message || '-'}
        </Text>
      </IndexTable.Cell>
      <IndexTable.Cell>
        {log.product && (
          <BlockStack gap="100">
            <Text variant="bodySm" as="span">
              {log.product.title}
            </Text>
            <Text variant="bodySm" tone="subdued" as="span">
              SKU: {log.product.sku || 'N/A'}
            </Text>
          </BlockStack>
        )}
      </IndexTable.Cell>
      <IndexTable.Cell>
        {log.error_message && (
          <Text variant="bodySm" tone="critical" as="span" truncate>
            {log.error_message}
          </Text>
        )}
      </IndexTable.Cell>
    </IndexTable.Row>
  ));

  return (
    <>
      <TitleBar title="Sync Logs" />
      <Page>
        <Layout>
        <Layout.Section>
          <BlockStack gap="400">
          <PageFeedback flash={flash} errors={errors} />

          <Card padding="0">
            <BlockStack gap="0">
              <Box padding="400">{filtersMarkup}</Box>
              <IndexTable
                resourceName={resourceName}
                itemCount={logs.data.length}
                headings={[
                  { title: 'Time' },
                  { title: 'Action' },
                  { title: 'Status' },
                  { title: 'Message' },
                  { title: 'Product' },
                  { title: 'Error' },
                ]}
                selectable={false}
              >
                {logs.data.length > 0 ? (
                  rowMarkup
                ) : (
                  <IndexTable.Row>
                    <IndexTable.Cell colSpan={6}>
                      <EmptyState
                        heading="No logs found"
                        image="https://cdn.shopify.com/s/files/1/0262/4071/2726/files/emptystate-files.png"
                      >
                        <p>Logs will appear here after running product syncs.</p>
                      </EmptyState>
                    </IndexTable.Cell>
                  </IndexTable.Row>
                )}
              </IndexTable>
            </BlockStack>
          </Card>

          {logs.data.length > 0 && (
            <Box paddingBlockStart="400">
              <InlineStack align="center">
              <Pagination
                hasPrevious={logs.current_page > 1}
                onPrevious={() => {
                  router.get(logs.prev_page_url);
                }}
                hasNext={logs.current_page < logs.last_page}
                onNext={() => {
                  router.get(logs.next_page_url);
                }}
              />
              </InlineStack>
            </Box>
          )}
          </BlockStack>
        </Layout.Section>
      </Layout>
    </Page>
    </>
  );
}
