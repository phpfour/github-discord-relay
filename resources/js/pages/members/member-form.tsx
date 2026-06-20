import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Identity = {
    source: 'github' | 'linear';
    external_id: string;
};

export type MemberFormData = {
    name: string;
    discord_user_id: string;
    identities: Identity[];
};

type Props = {
    initial: MemberFormData;
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
};

export default function MemberForm({ initial, submitUrl, method, submitLabel }: Props) {
    const form = useForm<MemberFormData>(initial);
    const { data, setData, errors, processing } = form;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(method, submitUrl);
    };

    const addIdentity = () => setData('identities', [...data.identities, { source: 'github', external_id: '' }]);

    const updateIdentity = (index: number, patch: Partial<Identity>) =>
        setData(
            'identities',
            data.identities.map((identity, i) => (i === index ? { ...identity, ...patch } : identity)),
        );

    const removeIdentity = (index: number) =>
        setData('identities', data.identities.filter((_, i) => i !== index));

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="discord_user_id">Discord user ID</Label>
                <Input
                    id="discord_user_id"
                    value={data.discord_user_id}
                    onChange={(e) => setData('discord_user_id', e.target.value)}
                    placeholder="538057585698537506"
                />
                <p className="text-xs text-muted-foreground">The raw Discord snowflake (no &lt;@ &gt; wrapper).</p>
                <InputError message={errors.discord_user_id} />
            </div>

            <div className="grid gap-3">
                <div className="flex items-center justify-between">
                    <Label>Identities</Label>
                    <Button type="button" variant="outline" size="sm" onClick={addIdentity}>
                        <Plus className="h-4 w-4" /> Add identity
                    </Button>
                </div>

                {data.identities.length === 0 && (
                    <p className="text-sm text-muted-foreground">No identities yet. Add a GitHub username or Linear UUID.</p>
                )}

                {data.identities.map((identity, index) => (
                    <div key={index} className="flex items-start gap-2">
                        <select
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            value={identity.source}
                            onChange={(e) => updateIdentity(index, { source: e.target.value as Identity['source'] })}
                        >
                            <option value="github">github</option>
                            <option value="linear">linear</option>
                        </select>
                        <div className="flex-1">
                            <Input
                                value={identity.external_id}
                                onChange={(e) => updateIdentity(index, { external_id: e.target.value })}
                                placeholder={identity.source === 'github' ? 'github-username' : 'linear-uuid'}
                            />
                            <InputError message={errors[`identities.${index}.external_id` as keyof typeof errors]} />
                        </div>
                        <Button type="button" variant="ghost" size="icon" onClick={() => removeIdentity(index)}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                ))}
            </div>

            <Button type="submit" disabled={processing}>
                {submitLabel}
            </Button>
        </form>
    );
}
