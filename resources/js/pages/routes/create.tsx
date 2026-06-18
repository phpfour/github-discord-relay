import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import RouteForm from './route-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Routes', href: '/routes' },
    { title: 'New', href: '/routes/create' },
];

export default function RouteCreate({ scopes }: { scopes: Record<'github' | 'linear', string[]> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New route" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="New route" description="Route a source scope to a Discord webhook." />
                <RouteForm
                    initial={{
                        source: 'github',
                        scope: 'repo',
                        match_value: '',
                        discord_webhook_url: '',
                        label: '',
                        is_active: true,
                    }}
                    scopes={scopes}
                    submitUrl="/routes"
                    method="post"
                    submitLabel="Create route"
                />
            </div>
        </AppLayout>
    );
}
