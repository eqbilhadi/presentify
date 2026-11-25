import { Form, Head, router } from '@inertiajs/react';
import { Building2, LogOut } from 'lucide-react';
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
    return (
        <>
            <Head title="Pilih Instansi" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">Pilih Instansi</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Akun Anda terdaftar di beberapa instansi. Pilih salah satu untuk masuk.
                    </p>
                </div>

                {tenants.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Tidak ada instansi aktif yang dapat diakses.
                    </div>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2" role="list">
                        {tenants.map((tenant) => (
                            <li key={tenant.id}>
                                <Card className="h-full transition-shadow hover:shadow-md">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start gap-3">
                                            <span
                                                className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary"
                                                aria-hidden="true"
                                            >
                                                <Building2 size={18} />
                                            </span>
                                            <CardTitle className="text-base leading-snug">
                                                {tenant.name}
                                            </CardTitle>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="pt-0">
                                        {tenant.terminology && (
                                            <p className="mb-4 text-xs text-muted-foreground">
                                                {tenant.terminology.employee ?? 'Pegawai'} &middot;{' '}
                                                {tenant.terminology.unit ?? 'Unit'}
                                            </p>
                                        )}

                                        <Form
                                            action={switchMethod.url()}
                                            method="post"
                                            className="w-full"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input type="hidden" name="tenant_id" value={tenant.id} />
                                                    <Button
                                                        type="submit"
                                                        className="w-full"
                                                        size="sm"
                                                        disabled={processing}
                                                        aria-label={`Masuk ke ${tenant.name}`}
                                                    >
                                                        {processing && <Spinner />}
                                                        Masuk
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
                        className="text-muted-foreground hover:text-destructive"
                        onClick={() =>
                            router.post(logoutSlo.url(), {}, { preserveScroll: false })
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
