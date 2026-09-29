import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { DocumentStatus, PaymentStatus } from '@/types/documents';

/** Colores por estado del documento. El texto llega ya traducido del servidor. */
const STATUS_CLASSES: Record<DocumentStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    issued: 'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100',
    sent: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950 dark:text-indigo-100',
    rectified: 'bg-purple-100 text-purple-900 dark:bg-purple-950 dark:text-purple-100',
    accepted: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
    rejected: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-100',
    converted: 'bg-teal-100 text-teal-900 dark:bg-teal-950 dark:text-teal-100',
};

/** Estado de cobro: es un marcador visual, la aplicación no gestiona pagos. */
const PAYMENT_CLASSES: Record<PaymentStatus, string> = {
    unpaid: 'bg-amber-100 text-amber-950 dark:bg-amber-950 dark:text-amber-100',
    paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
    overdue: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-100',
};

export function StatusBadge({ status, label, className }: { status: DocumentStatus; label: string; className?: string }) {
    return <Badge className={cn(STATUS_CLASSES[status], className)}>{label}</Badge>;
}

export function PaymentStatusBadge({ status, label, className }: { status: PaymentStatus; label: string; className?: string }) {
    return <Badge className={cn(PAYMENT_CLASSES[status], className)}>{label}</Badge>;
}
