import { InlineStack, Link, Text } from '@shopify/polaris';

export default function SectionHeader({ title, actionLabel = null, onAction = null }) {
  return (
    <InlineStack align="space-between" blockAlign="center">
      <Text variant="headingMd" as="h2">
        {title}
      </Text>

      {actionLabel && onAction && <Link onClick={onAction}>{actionLabel}</Link>}
    </InlineStack>
  );
}
