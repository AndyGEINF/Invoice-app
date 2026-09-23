import { Head, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { issuer } = usePage().props;

    return (
        <>
            <Head title="Panel" />
            <h1 className="text-2xl font-semibold">Panel</h1>
            <p className="mt-2 text-muted-foreground">
                {issuer.isComplete
                    ? `Bienvenido, ${issuer.name ?? issuer.legalName}.`
                    : 'Empieza completando los datos de tu empresa para poder emitir facturas.'}
            </p>
        </>
    );
}
