import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type WebhookRoute = {
    id: number;
    source: string;
    scope: string;
    match_value: string | null;
    discord_webhook_url: string;
    label: string | null;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Routes', href: '/routes' }];

export default function RoutesIndex({ routesBySource }: { routesBySource: Record<string, WebhookRoute[]> }) {
    const destroy = (route: WebhookRoute) => {
        if (confirm('Delete this route?')) {
            router.delete(`/routes/${route.id}`);
        }
    };

    const sources = Object.keys(routesBySource);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Routes" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Routes" description="Destination Discord webhooks, resolved most-specific-match first." />
                    <Button asChild>
                        <Link href="/routes/create">
                            <Plus className="h-4 w-4" /> New route
                        </Link>
                    </Button>
                </div>

                {sources.length === 0 && <p className="text-sm text-muted-foreground">No routes yet.</p>}

                {sources.map((source) => (
                    <div key={source} className="space-y-2">
                        <h3 className="text-sm font-semibold capitalize">{source}</h3>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Scope</th>
                                        <th className="px-4 py-2 font-medium">Match value</th>
                                        <th className="px-4 py-2 font-medium">Destination</th>
                                        <th className="px-4 py-2 font-medium">Status</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {routesBySource[source].map((route) => (
                                        <tr key={route.id} className="border-t">
                                            <td className="px-4 py-2">{route.scope}</td>
                                            <td className="px-4 py-2 font-mono text-xs">{route.match_value ?? '—'}</td>
                                            <td className="px-4 py-2">
                                                <span className="block max-w-xs truncate text-xs text-muted-foreground" title={route.discord_webhook_url}>
                                                    {route.label || route.discord_webhook_url}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2">
                                                <Badge variant={route.is_active ? 'default' : 'secondary'}>
                                                    {route.is_active ? 'active' : 'inactive'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-2">
                                                <div className="flex justify-end gap-1">
                                                    <Button asChild variant="ghost" size="icon">
                                                        <Link href={`/routes/${route.id}/edit`}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button variant="ghost" size="icon" onClick={() => destroy(route)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
