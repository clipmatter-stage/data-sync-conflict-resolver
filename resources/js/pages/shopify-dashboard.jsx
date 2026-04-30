import { Page, Card, Text, Button, BlockStack, InlineStack } from '@shopify/polaris';
import { Head } from '@inertiajs/react';

export default function ShopifyDashboard({ shop }) {
    return (
        <>
            <Head title="Shopify Dashboard" />
            <Page title="Welcome to Your Shopify App">
                <BlockStack gap="500">
                    <Card>
                        <BlockStack gap="300">
                            <Text as="h2" variant="headingMd">
                                🎉 Setup Complete!
                            </Text>
                            <Text as="p" variant="bodyMd">
                                Your Laravel + React + Shopify app is ready to go!
                            </Text>
                            {shop && (
                                <Text as="p" variant="bodyMd" tone="subdued">
                                    Connected to shop: <strong>{shop}</strong>
                                </Text>
                            )}
                        </BlockStack>
                    </Card>

                    <Card>
                        <BlockStack gap="300">
                            <Text as="h2" variant="headingMd">
                                ✅ What's Included
                            </Text>
                            <BlockStack gap="200">
                                <Text as="p" variant="bodyMd">
                                    • Laravel 12 (PHP 8.2 compatible)
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    • React with Inertia.js
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    • Shopify Polaris UI components
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    • All critical bug fixes applied
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    • Safari cross-origin compatibility
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    • Trial period protection
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    • Session token handling
                                </Text>
                            </BlockStack>
                        </BlockStack>
                    </Card>

                    <Card>
                        <BlockStack gap="300">
                            <Text as="h2" variant="headingMd">
                                🚀 Next Steps
                            </Text>
                            <BlockStack gap="200">
                                <Text as="p" variant="bodyMd">
                                    1. Update your <strong>.env</strong> file with Shopify API credentials
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    2. Set up ngrok for HTTPS: <code>ngrok http 8000</code>
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    3. Configure your app in Shopify Partners Dashboard
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    4. Start building your features!
                                </Text>
                            </BlockStack>
                            <InlineStack gap="300">
                                <Button>View Documentation</Button>
                                <Button variant="primary">Get Started</Button>
                            </InlineStack>
                        </BlockStack>
                    </Card>

                    <Card>
                        <BlockStack gap="300">
                            <Text as="h2" variant="headingMd">
                                📚 Resources
                            </Text>
                            <BlockStack gap="200">
                                <Text as="p" variant="bodyMd">
                                    • Setup Guide: <code>c:\xampp\htdocs\SETUP_GUIDE_FOR_PHP_8.2.md</code>
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    • Applied Fixes: <code>c:\xampp\htdocs\shopify-laravel-app\FIXES_APPLIED.md</code>
                                </Text>
                                <Text as="p" variant="bodyMd">
                                    • Quick Start: <code>c:\xampp\htdocs\shopify-laravel-app\QUICK_START_GUIDE.md</code>
                                </Text>
                            </BlockStack>
                        </BlockStack>
                    </Card>
                </BlockStack>
            </Page>
        </>
    );
}
