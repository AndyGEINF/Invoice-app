import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <>
            <Head title="Panel" />
            <main className="mx-auto max-w-5xl px-4 py-10">
                <h1 className="text-2xl font-semibold">INVOICE</h1>
                <p className="mt-2 text-muted-foreground">Facturas y presupuestos.</p>
            </main>
        </>
    );
}
