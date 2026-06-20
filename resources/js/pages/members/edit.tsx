import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import MemberForm from './member-form';

type Identity = { id: number; source: 'github' | 'linear'; external_id: string };
type Member = { id: number; name: string; discord_user_id: string; identities: Identity[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Members', href: '/members' },
    { title: 'Edit', href: '#' },
];

export default function MemberEdit({ member }: { member: Member }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${member.name}`} />
            <div className="flex flex-col gap-6 p-4">
                <Heading title={`Edit ${member.name}`} description="Update the member and their identities." />
                <MemberForm
                    initial={{
                        name: member.name,
                        discord_user_id: member.discord_user_id,
                        identities: member.identities.map((i) => ({ source: i.source, external_id: i.external_id })),
                    }}
                    submitUrl={`/members/${member.id}`}
                    method="put"
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
