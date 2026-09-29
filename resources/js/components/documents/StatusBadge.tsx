import { StatusPill, type Tone } from '@/components/StatusPill';
import type { DocumentStatus, PaymentStatus } from '@/types/documents';

/** Tono de cada estado del documento. El texto llega ya traducido del servidor. */
const STATUS_TONES: Record<DocumentStatus, Tone> = {
    draft: 'neutral',
    issued: 'info',
    sent: 'info',
    rectified: 'neutral',
    accepted: 'success',
    rejected: 'danger',
    converted: 'success',
};

/** Estado de cobro: es un marcador visual, la aplicación no gestiona pagos. */
const PAYMENT_TONES: Record<PaymentStatus, Tone> = {
    unpaid: 'warning',
    paid: 'success',
    overdue: 'danger',
};

export function StatusBadge({ status, label, className }: { status: DocumentStatus; label: string; className?: string }) {
    return <StatusPill tone={STATUS_TONES[status]} label={label} className={className} />;
}

export function PaymentStatusBadge({ status, label, className }: { status: PaymentStatus; label: string; className?: string }) {
    return <StatusPill tone={PAYMENT_TONES[status]} label={label} className={className} />;
}
