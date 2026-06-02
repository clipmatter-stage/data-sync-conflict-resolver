import {
  Page,
  Layout,
  Card,
  Box,
  Filters,
  ChoiceList,
  IndexTable,
  Text,
  Link,
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
import { withShopParam, withShopParams } from '@/utils/navigation';

export default function ConflictsIndex({ conflicts, filters }) {
  const { flash = {}, errors = {} } = usePage().props;
  const [status, setStatus] = useState(filters.status ? [filters.status] : []);
  const [fieldName, setFieldName] = useState(filters.field_name ? [filters.field_name] : []);
  const [queryValue, setQueryValue] = useState(filters.search || '');

  const handleStatusChange = useCallback((value) => setStatus(value), []);
  const handleFieldNameChange = useCallback((value) => setFieldName(value), []);
  const handleQueryValueChange = useCallback((value) => setQueryValue(value), []);

  const handleQueryValueRemove = useCallback(() => {
    setQueryValue('');
    applyFilters({ search: '' });
  }, []);

  const handleStatusRemove = useCallback(() => {
    setStatus([]);
    applyFilters({ status: '' });
  }, []);

  const handleFieldNameRemove = useCallback(() => {
    setFieldName([]);
    applyFilters({ field_name: '' });
  }, []);

  const handleClearAll = useCallback(() => {
    setStatus([]);
    setFieldName([]);
    setQueryValue('');
    router.get(route('product-sync.conflicts.index'), withShopParams());
  }, []);

  const applyFilters = (newFilters) => {
    const params = {
      status: status[0] || newFilters.status || '',
      field_name: fieldName[0] || newFilters.field_name || '',
      search: queryValue || newFilters.search || '',
    };

    // Remove empty values
    Object.keys(params).forEach((key) => {
      if (!params[key]) delete params[key];
    });

    router.get(route('product-sync.conflicts.index'), withShopParams(params), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const filtersMarkup = (
    <Filters
      queryValue={queryValue}
      queryPlaceholder="Search by product title or SKU"
      filters={[
        {
          key: 'status',
          label: 'Status',
          filter: (
            <ChoiceList
              title="Status"
              titleHidden
              choices={[
                { label: 'Pending', value: 'pending' },
                { label: 'Resolved', value: 'resolved' },
                { label: 'Ignored', value: 'ignored' },
                { label: 'Failed', value: 'failed' },
              ]}
              selected={status}
              onChange={handleStatusChange}
            />
          ),
          shortcut: true,
        },
        {
          key: 'field_name',
          label: 'Field',
          filter: (
            <ChoiceList
              title="Field Name"
              titleHidden
              choices={[
                { label: 'Title', value: 'title' },
                { label: 'SKU', value: 'sku' },
                { label: 'Description', value: 'description' },
                { label: 'Price', value: 'price' },
                { label: 'Vendor', value: 'vendor' },
                { label: 'Product Type', value: 'product_type' },
                { label: 'Status', value: 'status' },
                { label: 'Tags', value: 'tags' },
              ]}
              selected={fieldName}
              onChange={handleFieldNameChange}
            />
          ),
        },
      ]}
      appliedFilters={[
        ...(status.length > 0
          ? [
              {
                key: 'status',
                label: `Status: ${status[0]}`,
                onRemove: handleStatusRemove,
              },
            ]
          : []),
        ...(fieldName.length > 0
          ? [
              {
                key: 'field_name',
                label: `Field: ${fieldName[0]}`,
                onRemove: handleFieldNameRemove,
              },
            ]
          : []),
      ]}
      onQueryChange={handleQueryValueChange}
      onQueryClear={handleQueryValueRemove}
      onClearAll={handleClearAll}
    />
  );

  const resourceName = {
    singular: 'conflict',
    plural: 'conflicts',
  };

  const rowMarkup = conflicts.data.map((conflict, index) => (
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
        <Text variant="bodySm" as="span" truncate>
          {conflict.ps_value || 'N/A'}
        </Text>
      </IndexTable.Cell>
      <IndexTable.Cell>
        <Text variant="bodySm" as="span" truncate>
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
        <Link onClick={() => router.visit(withShopParam(route('product-sync.conflicts.show', conflict.id)))}>View Details</Link>
      </IndexTable.Cell>
    </IndexTable.Row>
  ));

  return (
    <>
      <TitleBar title="Product Sync Conflicts" />
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
                    itemCount={conflicts.data.length}
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
                    {conflicts.data.length > 0 ? (
                      rowMarkup
                    ) : (
                      <IndexTable.Row>
                        <IndexTable.Cell colSpan={7}>
                          <EmptyState
                            heading="No conflicts found"
                            image="https://cdn.shopify.com/s/files/1/0262/4071/2726/files/emptystate-files.png"
                          >
                            <p>Try adjusting your filters or run a product sync.</p>
                          </EmptyState>
                        </IndexTable.Cell>
                      </IndexTable.Row>
                    )}
                  </IndexTable>
                </BlockStack>
              </Card>

              {conflicts.data.length > 0 && (
                <Box paddingBlockStart="400">
                  <InlineStack align="center">
                    <Pagination
                      hasPrevious={conflicts.current_page > 1}
                      onPrevious={() => {
                        router.get(conflicts.prev_page_url);
                      }}
                      hasNext={conflicts.current_page < conflicts.last_page}
                      onNext={() => {
                        router.get(conflicts.next_page_url);
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
