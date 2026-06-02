import { Banner, BlockStack } from '@shopify/polaris';

export default function PageFeedback({ flash = {}, errors = {}, error = null, info = null }) {
  const firstValidationError = Object.values(errors || {})[0];
  const criticalMessage = flash.error || error || firstValidationError;

  if (!flash.success && !criticalMessage && !info) {
    return null;
  }

  return (
    <BlockStack gap="300">
      {flash.success && (
        <Banner tone="success" title="Success">
          <p>{flash.success}</p>
        </Banner>
      )}

      {criticalMessage && (
        <Banner tone="critical" title="Action failed">
          <p>{criticalMessage}</p>
        </Banner>
      )}

      {info && (
        <Banner tone="info" title="In progress">
          <p>{info}</p>
        </Banner>
      )}
    </BlockStack>
  );
}
