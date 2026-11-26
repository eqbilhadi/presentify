import { Form, Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, Building2, LogOut } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { slo as logoutSlo } from '@/routes/logout';
import { switchMethod } from '@/routes/workspace';

type TenantItem = {
    id: string;
    name: string;
    slug: string;
    terminology: Record<string, string> | null;
    theme_config: Record<string, string> | null;
};

type Props = {
    tenants: TenantItem[];
};

export default function TenantPicker({ tenants }: Props) {
    const { errors } = usePage().props as {
        errors?: Record<string, string>;
    };
    const switchError = errors?.tenant_id;

    return (
        <>
            <Head title="Pilih Instansi" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Pilih Instansi
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Akun Anda terdaftar di{' '}
                        <span className="font-medium text-foreground tabular-nums">
                            {tenants.length}
                        </span>{' '}
                        instansi. Pilih salah satu untuk melanjutkan.
                    </p>
                </div>

                {switchError && (
                    <Alert variant="destructive">
                        <AlertCircle aria-hidden="true" />
                        <AlertDescription>{switchError}</AlertDescription>
                    </Alert>
                )}

                {tenants.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed p-8 text-center">
                        <Building2
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div className="space-y-1">
                            <p className="text-sm font-medium">
                                Belum ada instansi aktif
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Hubungi administrator instansi Anda atau keluar
                                untuk masuk dengan akun lain.
                            </p>
                        </div>
                    </div>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2" role="list">
                        {tenants.map((tenant) => (
                            <li key={tenant.id}>
                                <Card className="h-full border transition-colors hover:border-foreground/25">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start gap-3">
                                            <span
                                                className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md border bg-muted/50 text-foreground"
                                                aria-hidden="true"
                                            >
                                                <Building2 size={18} />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <CardTitle className="truncate text-base leading-snug">
                                                    {tenant.name}
                                                </CardTitle>
                                                <p className="mt-0.5 truncate font-mono text-xs text-muted-foreground">
                                                    {tenant.slug}
                                                </p>
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="pt-0">
                                        {tenant.terminology && (
                                            <p className="mb-4 text-xs text-muted-foreground">
                                                {tenant.terminology.employee ??
                                                    'Pegawai'}{' '}
                                                &middot;{' '}
                                                {tenant.terminology.unit ??
                                                    'Unit'}
                                            </p>
                                        )}

                                        <Form
                                            action={switchMethod.url()}
                                            method="post"
                                            className="w-full"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="tenant_id"
                                                        value={tenant.id}
                                                    />
                                                    <Button
                                                        type="submit"
                                                        className="min-h-11 w-full sm:min-h-9"
                                                        size="sm"
                                                        disabled={processing}
                                                        aria-label={`Masuk ke ${tenant.name}`}
                                                    >
                                                        {processing && (
                                                            <Spinner />
                                                        )}
                                                        {processing
                                                            ? 'Memasuki...'
                                                            : 'Masuk'}
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="border-t pt-4">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="min-h-11 text-muted-foreground hover:text-destructive sm:min-h-9"
                        onClick={() =>
                            router.post(
                                logoutSlo.url(),
                                {},
                                { preserveScroll: false },
                            )
                        }
                    >
                        <LogOut size={15} aria-hidden="true" />
                        Keluar dari semua sesi
                    </Button>
                </div>
            </div>
        </>
    );
}

TenantPicker.layout = {
    title: 'Pilih Instansi',
    description: 'Pilih instansi yang ingin Anda masuki',
};
