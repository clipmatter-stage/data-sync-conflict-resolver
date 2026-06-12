import { Page, Layout, Card, BlockStack, Text } from '@shopify/polaris';
import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { TitleBar } from '@shopify/app-bridge-react';
import axios from 'axios';
import Swal from 'sweetalert2';
import SyncStatsCards from '@/components/ProductSync/SyncStatsCards';
import ConflictTable from '@/components/ProductSync/ConflictTable';
import RecentSyncLogs from '@/components/ProductSync/RecentSyncLogs';
import PageFeedback from '@/components/ProductSync/PageFeedback';
import SectionHeader from '@/components/ProductSync/SectionHeader';
import { withShopParam } from '@/utils/navigation';

export default function Dashboard({ stats, recentConflicts, recentLogs }) {
  const { flash = {} } = usePage().props;
  const [syncing, setSyncing] = useState(false);
  const [syncError, setSyncError] = useState(null);

  const pollJobStatus = () => {
    const interval = setInterval(async () => {
      try {
        const { data } = await axios.get(withShopParam(route('product-sync.status')));
        
        if (data.status === 'completed') {
          clearInterval(interval);
          setSyncing(false);
          Swal.fire({
            icon: 'success',
            title: 'Sync Completed',
            text: data.message || 'Product sync completed successfully.',
            confirmButtonText: 'Great'
          }).then(() => {
            router.reload({ only: ['stats', 'recentConflicts', 'recentLogs'] });
          });
        } else if (data.status === 'failed') {
          clearInterval(interval);
          setSyncing(false);
          setSyncError(data.message || 'Product sync failed.');
          Swal.fire({
            icon: 'error',
            title: 'Sync Failed',
            text: data.message || 'Product sync failed.',
            confirmButtonText: 'Close'
          }).then(() => {
            router.reload({ only: ['stats', 'recentConflicts', 'recentLogs'] });
          });
        } else if (data.status === 'running') {
          Swal.update({
             text: data.message || 'Product sync is running... This may take a few minutes.'
          });
        }
      } catch (err) {
         // Silently fail polling; will retry on next interval
      }
    }, 3000);
  };

  const handleSync = async () => {
    setSyncing(true);
    setSyncError(null);
    
    Swal.fire({
      title: 'Syncing Products',
      text: 'Starting product sync... Please wait.',
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    try {
      await axios.post(withShopParam(route('product-sync.sync')));
      pollJobStatus();
    } catch (error) {
      setSyncing(false);
      setSyncError('Could not start product sync. Please try again.');
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Could not start product sync. Please try again.'
      });
    }
  };

  const lastSyncText = stats.last_sync_at
    ? `Last synced: ${new Date(stats.last_sync_at).toLocaleString()}`
    : 'Never synced';

  return (
    <>
      <TitleBar title="Product Sync Conflict Resolver">
        <button variant="primary" onClick={handleSync} disabled={syncing}>
          {syncing ? 'Syncing…' : 'Sync Products'}
        </button>
      </TitleBar>
      <Page>
      <Layout>
        <Layout.Section>
          <BlockStack gap="500">
            <PageFeedback
              flash={flash}
              error={syncError}
              info={
                syncing
                  ? 'Product sync is running. This may take a few minutes depending on the number of products.'
                  : null
              }
            />

            <Card>
              <BlockStack gap="300">
                <Text variant="headingMd" as="h2">
                  Sync Statistics
                </Text>
                <Text variant="bodySm" tone="subdued">
                  {lastSyncText}
                </Text>
                <SyncStatsCards stats={stats} />
              </BlockStack>
            </Card>

            {recentConflicts.length > 0 && (
              <Card>
                <BlockStack gap="400">
                  <SectionHeader
                    title="Recent Pending Conflicts"
                    actionLabel="View All"
                    onAction={() => router.visit(withShopParam(route('product-sync.conflicts.index')))}
                  />
                  <ConflictTable
                    conflicts={recentConflicts}
                    emptyMessage="No pending conflicts"
                  />
                </BlockStack>
              </Card>
            )}

            <Card>
              <BlockStack gap="400">
                <SectionHeader
                  title="Recent Sync Logs"
                  actionLabel="View All"
                  onAction={() => router.visit(withShopParam(route('product-sync.logs.index')))}
                />
                <RecentSyncLogs logs={recentLogs} />
              </BlockStack>
            </Card>
          </BlockStack>
        </Layout.Section>
      </Layout>
    </Page>
    </>
  );
}
