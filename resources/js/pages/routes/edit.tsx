import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import RouteForm from './route-form';

type WebhookRoute = {
    id: number;
    source: 'github' | 'linear';
    scope: string;
    match_value: string | null;
    discord_webhook_url: string;
    label: string | null;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Routes', href: '/routes' },
    { title: 'Edit', href: '#' },
];

export default function RouteEdit({
    route,
    scopes,
}: {
    route: WebhookRoute;
    scopes: Record<'github' | 'linear', string[]>;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit route" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="Edit route" description="Update this destination route." />
                <RouteForm
                    initial={{
                        source: route.source,
                        scope: route.scope,
                        match_value: route.match_value ?? '',
                        discord_webhook_url: route.discord_webhook_url,
                        label: route.label ?? '',
                        is_active: route.is_active,
                    }}
                    scopes={scopes}
                    submitUrl={`/routes/${route.id}`}
                    method="put"
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
