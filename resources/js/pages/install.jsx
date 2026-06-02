import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function Install() {
    const [shop, setShop] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        setLoading(true);
        
        // Normalize shop domain
        let shopDomain = shop.trim();
        if (!shopDomain.includes('.myshopify.com')) {
            shopDomain = `${shopDomain}.myshopify.com`;
        }
        
        // Redirect to authenticate with shop parameter
        window.location.href = `/authenticate?shop=${shopDomain}`;
    };

    return (
        <>
            <Head title="Install App" />
            
            <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <CardTitle className="text-2xl">Product Sync & Conflict Resolver</CardTitle>
                        <CardDescription>
                            Enter your Shopify store domain to install the app
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="shop">Shopify Store Domain</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="shop"
                                        type="text"
                                        placeholder="your-store"
                                        value={shop}
                                        onChange={(e) => setShop(e.target.value)}
                                        required
                                        className="flex-1"
                                    />
                                    <span className="flex items-center text-sm text-muted-foreground">
                                        .myshopify.com
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Example: abdulrahmandev
                                </p>
                            </div>
                            
                            <Button 
                                type="submit" 
                                className="w-full" 
                                disabled={loading || !shop}
                            >
                                {loading ? 'Connecting...' : 'Install App'}
                            </Button>
                            
                            <div className="text-xs text-center text-muted-foreground space-y-1">
                                <p>Already installed?</p>
                                <p>Access the app from your Shopify Admin → Apps</p>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
