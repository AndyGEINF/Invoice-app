import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { Mail, Unplug } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { Field, FormSection } from '@/components/FormField';
import { PageHeader } from '@/components/PageHeader';
import { SettingsTabs } from '@/components/SettingsTabs';
import { StatusPill } from '@/components/StatusPill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Provider = 'gmail' | 'brevo' | 'custom';

interface Props {
    mail: {
        provider: Provider | null;
        from_address: string | null;
        username: string | null;
        host: string | null;
        port: number | null;
        has_password: boolean;
        configured: boolean;
        test_status: 'pending' | 'ok' | 'failed' | null;
        test_error: string | null;
        tested_at: string | null;
    };
    providers: { value: Provider; label: string }[];
}

const GMAIL_APP_PASSWORDS_URL = 'https://myaccount.google.com/apppasswords';
const BREVO_SMTP_URL = 'https://app.brevo.com/settings/keys/smtp';

/** Cada cuánto se mira si ya llegó el resultado del correo de prueba. */
const POLL_INTERVAL_MS = 2000;

const PASSWORD_LABELS: Record<Provider, string> = {
    gmail: 'Contraseña de aplicación',
    brevo: 'Clave SMTP',
    custom: 'Contraseña',
};

/**
 * Ajustes > Correo: la cuenta desde la que salen las facturas y presupuestos.
 * Al guardar se manda un correo de prueba a la propia cuenta y el resultado
 * aparece arriba en cuanto llega.
 */
export default function MailSettings({ mail, providers }: Props) {
    const form = useForm({
        provider: mail.provider ?? ('gmail' as Provider),
        from_address: mail.from_address ?? '',
        username: mail.username ?? '',
        password: '',
        host: mail.host ?? '',
        port: mail.port ? String(mail.port) : '',
    });
    const errors = form.errors as Record<string, string>;
    const provider = form.data.provider;
    const pending = mail.test_status === 'pending';
    const { start, stop } = usePoll(POLL_INTERVAL_MS, { only: ['mail'] }, { autoStart: false });

    // La prueba la manda la cola: mientras no hay resultado, se va mirando.
    useEffect(() => {
        if (pending) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [pending, start, stop]);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put('/settings/mail', { preserveScroll: true, onSuccess: () => form.setData('password', '') });
    }

    function disconnect() {
        if (window.confirm('¿Desconectar tu correo? Los envíos dejarán de llegar a tus clientes hasta que lo conectes de nuevo.')) {
            router.delete('/settings/mail', { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Correo" />

            <form onSubmit={submit} className="mx-auto max-w-4xl">
                <SettingsTabs />

                <PageHeader
                    title="Correo"
                    description="La cuenta desde la que envías las facturas y los presupuestos a tus clientes."
                    actions={
                        <Button type="submit" disabled={form.processing || pending}>
                            <Mail aria-hidden />
                            {form.processing ? 'Guardando…' : 'Guardar y enviar prueba'}
                        </Button>
                    }
                />

                <ConnectionStatus mail={mail} onDisconnect={disconnect} />

                <FormSection title="Servicio de correo" description="Elige dónde tienes tu cuenta.">
                    <div role="radiogroup" aria-label="Servicio de correo" className="grid gap-2 sm:grid-cols-3">
                        {providers.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="radio"
                                aria-checked={provider === option.value}
                                onClick={() => form.setData('provider', option.value)}
                                className={cn(
                                    'h-9 rounded-lg border px-3 text-sm font-medium transition-colors hover:bg-muted',
                                    provider === option.value && 'border-accent-foreground/30 bg-accent text-accent-foreground hover:bg-accent',
                                )}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>

                    <div className="mt-4 rounded-lg bg-muted px-4 py-3 text-sm">
                        <ProviderSteps provider={provider} />
                    </div>
                </FormSection>

                <FormSection title="Tus datos" description="Se guardan en tu instalación. La contraseña va cifrada y no se vuelve a mostrar.">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            id="from_address"
                            label="Email desde el que envías"
                            required
                            hint={provider === 'gmail' ? 'Tu dirección de Gmail.' : 'Tiene que estar verificada en tu servicio de correo.'}
                            error={errors.from_address}
                        >
                            <Input
                                id="from_address"
                                type="email"
                                autoComplete="email"
                                value={form.data.from_address}
                                onChange={(event) => form.setData('from_address', event.target.value)}
                            />
                        </Field>

                        {provider !== 'gmail' ? (
                            <Field id="username" label="Usuario" required hint={provider === 'brevo' ? 'El "Login" de la página SMTP de Brevo.' : undefined} error={errors.username}>
                                <Input id="username" autoComplete="off" value={form.data.username} onChange={(event) => form.setData('username', event.target.value)} />
                            </Field>
                        ) : null}

                        <Field
                            id="password"
                            label={PASSWORD_LABELS[provider]}
                            required={!mail.has_password}
                            hint={mail.has_password ? 'Ya hay una guardada. Escribe otra solo si quieres cambiarla.' : undefined}
                            error={errors.password}
                        >
                            <Input
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password}
                                placeholder={mail.has_password ? '••••••••••••••••' : undefined}
                                onChange={(event) => form.setData('password', event.target.value)}
                            />
                        </Field>

                        {provider === 'custom' ? (
                            <div className="grid grid-cols-[1fr_6rem] gap-4 md:col-span-2">
                                <Field id="host" label="Servidor SMTP" required error={errors.host}>
                                    <Input id="host" placeholder="smtp.tuproveedor.com" value={form.data.host} onChange={(event) => form.setData('host', event.target.value)} />
                                </Field>
                                <Field id="port" label="Puerto" required error={errors.port}>
                                    <Input id="port" inputMode="numeric" placeholder="587" value={form.data.port} onChange={(event) => form.setData('port', event.target.value.replace(/\D/g, ''))} />
                                </Field>
                            </div>
                        ) : null}
                    </div>
                </FormSection>
            </form>
        </>
    );
}

function ConnectionStatus({ mail, onDisconnect }: { mail: Props['mail']; onDisconnect: () => void }) {
    let status: ReactNode;

    if (mail.test_status === 'pending') {
        status = <StatusRow tone="neutral" label="Probando" text={`Te estamos enviando un correo de prueba a ${mail.from_address}.`} />;
    } else if (mail.test_status === 'failed') {
        status = <StatusRow tone="danger" label="Error" text={mail.test_error ?? 'No se ha podido enviar el correo de prueba.'} />;
    } else if (mail.configured) {
        status = (
            <StatusRow
                tone="success"
                label="Conectado"
                text={`Tus documentos salen desde ${mail.from_address}. Mira tu bandeja de entrada: te hemos enviado un correo de prueba.`}
            />
        );
    } else {
        status = <StatusRow tone="warning" label="Sin conectar" text="Los envíos no llegarán a tus clientes hasta que conectes tu correo." />;
    }

    return (
        <FormSection
            title="Estado"
            actions={
                mail.configured ? (
                    <Button type="button" variant="ghost" className="text-destructive hover:text-destructive" onClick={onDisconnect}>
                        <Unplug aria-hidden />
                        Desconectar
                    </Button>
                ) : null
            }
        >
            {status}
        </FormSection>
    );
}

function StatusRow({ tone, label, text }: { tone: 'neutral' | 'success' | 'warning' | 'danger'; label: string; text: string }) {
    return (
        <div className="flex flex-col gap-1 text-sm">
            <StatusPill tone={tone} label={label} className="self-start" />
            <p className="break-words whitespace-pre-line text-muted-foreground">{text}</p>
        </div>
    );
}

function ProviderSteps({ provider }: { provider: Provider }) {
    if (provider === 'gmail') {
        return (
            <ol className="list-decimal space-y-1 pl-5">
                <li>Activa la verificación en dos pasos en tu cuenta de Google, si aún no la tienes.</li>
                <li>
                    Crea una contraseña de aplicación en{' '}
                    <a href={GMAIL_APP_PASSWORDS_URL} target="_blank" rel="noreferrer" className="font-medium text-primary underline underline-offset-2">
                        myaccount.google.com/apppasswords
                    </a>
                    . Ponle de nombre «Invoice».
                </li>
                <li>Copia las 16 letras que te da Google y pégalas abajo, con tu dirección de Gmail.</li>
            </ol>
        );
    }

    if (provider === 'brevo') {
        return (
            <ol className="list-decimal space-y-1 pl-5">
                <li>Crea una cuenta gratuita en Brevo y verifica la dirección desde la que vas a enviar.</li>
                <li>
                    En{' '}
                    <a href={BREVO_SMTP_URL} target="_blank" rel="noreferrer" className="font-medium text-primary underline underline-offset-2">
                        SMTP y API
                    </a>{' '}
                    copia el «Login» y genera una clave SMTP.
                </li>
                <li>Pégalos abajo, junto con la dirección verificada.</li>
            </ol>
        );
    }

    return <p>Pide a tu proveedor de correo los datos SMTP: servidor, puerto (normalmente 587), usuario y contraseña.</p>;
}
