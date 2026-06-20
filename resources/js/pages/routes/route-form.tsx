import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Source = 'github' | 'linear';

export type RouteFormData = {
    source: Source;
    scope: string;
    match_value: string;
    discord_webhook_url: string;
    label: string;
    is_active: boolean;
};

type Props = {
    initial: RouteFormData;
    scopes: Record<Source, string[]>;
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
};

export default function RouteForm({ initial, scopes, submitUrl, method, submitLabel }: Props) {
    const form = useForm<RouteFormData>(initial);
    const { data, setData, errors, processing } = form;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(method, submitUrl);
    };

    const onSourceChange = (source: Source) => {
        const allowed = scopes[source];
        setData((current) => ({
            ...current,
            source,
            scope: allowed.includes(current.scope) ? current.scope : allowed[0],
        }));
    };

    const isGlobal = data.scope === 'global';

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="source">Source</Label>
                <select
                    id="source"
                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    value={data.source}
                    onChange={(e) => onSourceChange(e.target.value as Source)}
                >
                    <option value="github">github</option>
                    <option value="linear">linear</option>
                </select>
                <InputError message={errors.source} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="scope">Scope</Label>
                <select
                    id="scope"
                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    value={data.scope}
                    onChange={(e) => setData('scope', e.target.value)}
                >
                    {scopes[data.source].map((scope) => (
                        <option key={scope} value={scope}>
                            {scope}
                        </option>
                    ))}
                </select>
                <InputError message={errors.scope} />
            </div>

            {!isGlobal && (
                <div className="grid gap-2">
                    <Label htmlFor="match_value">Match value</Label>
                    <Input
                        id="match_value"
                        value={data.match_value}
                        onChange={(e) => setData('match_value', e.target.value)}
                        placeholder={data.source === 'github' ? 'owner/repo or owner' : 'project or team id'}
                    />
                    <InputError message={errors.match_value} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="discord_webhook_url">Discord webhook URL</Label>
                <Input
                    id="discord_webhook_url"
                    value={data.discord_webhook_url}
                    onChange={(e) => setData('discord_webhook_url', e.target.value)}
                    placeholder="https://discord.com/api/webhooks/..."
                />
                <InputError message={errors.discord_webhook_url} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="label">Label</Label>
                <Input id="label" value={data.label} onChange={(e) => setData('label', e.target.value)} />
                <InputError message={errors.label} />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="is_active"
                    checked={data.is_active}
                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                />
                <Label htmlFor="is_active">Active</Label>
            </div>

            <Button type="submit" disabled={processing}>
                {submitLabel}
            </Button>
        </form>
    );
}
