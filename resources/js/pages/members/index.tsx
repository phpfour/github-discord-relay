import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Identity = { id: number; source: string; external_id: string };
type Member = { id: number; name: string; discord_user_id: string; identities: Identity[] };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Members', href: '/members' }];

export default function MembersIndex({ members }: { members: Member[] }) {
    const destroy = (member: Member) => {
        if (confirm(`Delete ${member.name}? This removes their identities too.`)) {
            router.delete(`/members/${member.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Members" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Members" description="People and their GitHub / Linear identities mapped to Discord." />
                    <Button asChild>
                        <Link href="/members/create">
                            <Plus className="h-4 w-4" /> New member
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-2 font-medium">Name</th>
                                <th className="px-4 py-2 font-medium">Discord ID</th>
                                <th className="px-4 py-2 font-medium">Identities</th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {members.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-4 py-6 text-center text-muted-foreground">
                                        No members yet.
                                    </td>
                                </tr>
                            )}
                            {members.map((member) => (
                                <tr key={member.id} className="border-t">
                                    <td className="px-4 py-2 font-medium">{member.name}</td>
                                    <td className="px-4 py-2 font-mono text-xs">{member.discord_user_id}</td>
                                    <td className="px-4 py-2">
                                        <div className="flex flex-wrap gap-1">
                                            {member.identities.map((identity) => (
                                                <Badge key={identity.id} variant="secondary">
                                                    {identity.source}: {identity.external_id}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-2">
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon">
                                                <Link href={`/members/${member.id}/edit`}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => destroy(member)}>
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
        </AppLayout>
    );
}
