import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import MemberForm from './member-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Members', href: '/members' },
    { title: 'New', href: '/members/create' },
];

export default function MemberCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New member" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="New member" description="Add a teammate and their source identities." />
                <MemberForm
                    initial={{ name: '', discord_user_id: '', identities: [] }}
                    submitUrl="/members"
                    method="post"
                    submitLabel="Create member"
                />
            </div>
        </AppLayout>
    );
}
