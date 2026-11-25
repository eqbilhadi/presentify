import { Form, Head } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { login } from '@/routes';
import { send, store } from '@/routes/auth/otp';

type Props = {
    email: string;
    status?: string;
};

export default function VerifyOtp({ email, status }: Props) {
    useEffect(() => {
        if (status) toast.success(status);
    }, [status]);

    return (
        <>
            <Head title="Verifikasi OTP" />

            <div className="flex flex-col gap-6">
                <Form
                    {...store.form()}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2 text-center">
                                <p className="text-sm text-muted-foreground">
                                    Kode 6-digit telah dikirim ke{' '}
                                    <span className="font-medium text-foreground">{email || 'email Anda'}</span>
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="code" className="text-center">
                                    Kode OTP
                                </Label>
                                <div className="flex justify-center">
                                    <InputOTP
                                        id="code"
                                        name="code"
                                        maxLength={6}
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            <InputOTPSlot index={0} />
                                            <InputOTPSlot index={1} />
                                            <InputOTPSlot index={2} />
                                            <InputOTPSlot index={3} />
                                            <InputOTPSlot index={4} />
                                            <InputOTPSlot index={5} />
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError message={errors.code} className="text-center" />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Verifikasi
                            </Button>
                        </>
                    )}
                </Form>

                <div className="flex flex-col items-center gap-3 text-center text-sm">
                    {email && (
                        <Form {...send.form()} className="w-full">
                            {({ processing }) => (
                                <>
                                    <Input type="hidden" name="email" value={email} />
                                    <div className="text-muted-foreground">
                                        Tidak menerima kode?{' '}
                                        <Button
                                            type="submit"
                                            variant="link"
                                            className="h-auto p-0 text-sm font-medium"
                                            disabled={processing}
                                        >
                                            {processing ? 'Mengirim...' : 'Kirim ulang kode'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    )}

                    <div className="text-muted-foreground">
                        <TextLink href={login()}>
                            Kembali ke halaman login
                        </TextLink>
                    </div>
                </div>
            </div>
        </>
    );
}

VerifyOtp.layout = {
    title: 'Verifikasi Kode OTP',
    description: 'Masukkan kode 6-digit yang dikirim ke email Anda',
};
