import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Relay Settings', href: '/relay-settings' }];

type Props = {
    githubSecretConfigured: boolean;
    linearSecretConfigured: boolean;
    linearSkipFilter: string;
};

type FormData = {
    github_webhook_secret: string;
    linear_webhook_secret: string;
    linear_skip_filter: string;
};

export default function RelaySettings({ githubSecretConfigured, linearSecretConfigured, linearSkipFilter }: Props) {
    const form = useForm<FormData>({
        github_webhook_secret: '',
        linear_webhook_secret: '',
        linear_skip_filter: linearSkipFilter,
    });
    const { data, setData, errors, processing, recentlySuccessful } = form;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/relay-settings', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Relay settings" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Relay settings"
                    description="Per-source signing secrets and the Linear skip filter."
                />

                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="github_webhook_secret">GitHub webhook secret</Label>
                        <Input
                            id="github_webhook_secret"
                            type="password"
                            autoComplete="off"
                            value={data.github_webhook_secret}
                            onChange={(e) => setData('github_webhook_secret', e.target.value)}
                            placeholder={githubSecretConfigured ? '•••••• (configured)' : 'not set'}
                        />
                        <p className="text-xs text-muted-foreground">
                            Enables HMAC-SHA256 verification of <code>X-Hub-Signature-256</code>. Submit blank to clear.
                        </p>
                        <InputError message={errors.github_webhook_secret} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="linear_webhook_secret">Linear webhook secret</Label>
                        <Input
                            id="linear_webhook_secret"
                            type="password"
                            autoComplete="off"
                            value={data.linear_webhook_secret}
                            onChange={(e) => setData('linear_webhook_secret', e.target.value)}
                            placeholder={linearSecretConfigured ? '•••••• (configured)' : 'not set'}
                        />
                        <p className="text-xs text-muted-foreground">
                            Enables verification of the <code>Linear-Signature</code> header. Submit blank to clear.
                        </p>
                        <InputError message={errors.linear_webhook_secret} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="linear_skip_filter">Linear skip filter (JSON)</Label>
                        <textarea
                            id="linear_skip_filter"
                            className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs"
                            value={data.linear_skip_filter}
                            onChange={(e) => setData('linear_skip_filter', e.target.value)}
                        />
                        <p className="text-xs text-muted-foreground">
                            Type to action map, e.g. <code>{'{"issue":["update"]}'}</code> suppresses issue updates.
                        </p>
                        <InputError message={errors.linear_skip_filter} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            Save settings
                        </Button>
                        {recentlySuccessful && <span className="text-sm text-muted-foreground">Saved.</span>}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
