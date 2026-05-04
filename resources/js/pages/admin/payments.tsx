import { FormEventHandler, useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { toast } from 'sonner';
import admin from '@/routes/admin';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type CalendarOption = {
    value: string;
    label: string;
};

type PreRegistrationRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
};

type EnrollmentRow = {
    enrollment_id: number;
    student_id: number;
    course_id: number;
    student_name: string;
    student_email: string;
    course_label: string;
    last_payment_at: string | null;
    last_payment_in_selected_month_at: string | null;
};

type PaymentsProps = {
    calendarMonths: CalendarOption[];
    calendarYears: CalendarOption[];
    selectedMonth: string;
    rows: EnrollmentRow[];
    preRegistrations: PreRegistrationRow[];
};

type PaymentMethod = 'efectivo' | 'transferencia' | 'otro';

type PaymentFormData = {
    student_id: number;
    course_id: number;
    amount: string;
    method: PaymentMethod;
    reference: string;
    receipt: File | null;
    paid_at: string;
    due_at: string;
    notes: string;
    return_month: string;
};

function toDatetimeLocalValue(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function formatDateEs(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    try {
        return new Date(iso).toLocaleDateString('es', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return '—';
    }
}

function emptyPaymentForm(returnMonth: string): PaymentFormData {
    return {
        student_id: 0,
        course_id: 0,
        amount: '',
        method: 'transferencia',
        reference: '',
        receipt: null,
        paid_at: toDatetimeLocalValue(new Date()),
        due_at: '',
        notes: '',
        return_month: returnMonth,
    };
}

function splitSelectedMonth(selectedMonth: string): {
    year: string;
    month: string;
} {
    const match = /^(\d{4})-(\d{2})$/.exec(selectedMonth);

    if (match) {
        return { year: match[1], month: match[2] };
    }

    const now = new Date();

    return {
        year: String(now.getFullYear()),
        month: String(now.getMonth() + 1).padStart(2, '0'),
    };
}

export default function AdminPayments({
    calendarMonths,
    calendarYears,
    selectedMonth,
    rows,
    preRegistrations,
}: PaymentsProps) {
    const [tab, setTab] = useState<'alumnos' | 'mas'>('alumnos');
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);

    const receiptRef = useRef<HTMLInputElement>(null);

    const form = useForm<PaymentFormData>(emptyPaymentForm(selectedMonth));

    useEffect(() => {
        form.setData('return_month', selectedMonth);
    }, [selectedMonth]);

    function openPaymentModal(row: EnrollmentRow): void {
        form.clearErrors();
        form.setData({
            ...emptyPaymentForm(selectedMonth),
            student_id: row.student_id,
            course_id: row.course_id,
            paid_at: toDatetimeLocalValue(new Date()),
            return_month: selectedMonth,
        });
        if (receiptRef.current) {
            receiptRef.current.value = '';
        }
        setPaymentModalOpen(true);
    }

    function closePaymentModal(): void {
        setPaymentModalOpen(false);
        form.reset();
        form.clearErrors();
        if (receiptRef.current) {
            receiptRef.current.value = '';
        }
    }

    const { year: selectedYear, month: selectedMonthPart } =
        splitSelectedMonth(selectedMonth);

    function navigatePaymentsMonth(year: string, monthPadded: string): void {
        router.get(
            admin.payments.index.url({
                query: { month: `${year}-${monthPadded}` },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }

    const submitPayment: FormEventHandler = (e) => {
        e.preventDefault();

        form.post(admin.payments.store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Pago registrado.');
                closePaymentModal();
            },
            onError: () => {
                toast.error('No se pudo registrar el pago.');
            },
        });
    };

    const cashMode = form.data.method === 'efectivo';

    return (
        <>
            <Head title="Pagos" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-2xl font-semibold">Pagos</h1>
                        <p className="text-sm text-muted-foreground">
                            Gestiona cobros y registra comprobantes por alumno y
                            curso.
                        </p>
                    </div>

                    <div className="inline-flex gap-1 rounded-lg bg-muted/60 p-1 dark:bg-muted/30">
                        <button
                            type="button"
                            onClick={() => setTab('alumnos')}
                            className={cn(
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                tab === 'alumnos'
                                    ? 'bg-background shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Alumnos
                        </button>
                        <button
                            type="button"
                            onClick={() => setTab('mas')}
                            className={cn(
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                tab === 'mas'
                                    ? 'bg-background shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Preinscritos
                        </button>
                    </div>

                    {tab === 'alumnos' ? (
                        <>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                                <div className="grid gap-2">
                                    <span className="text-sm font-medium">
                                        Mes de referencia
                                    </span>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <div className="grid gap-1">
                                            <Label htmlFor="payments-month-part">
                                                Mes
                                            </Label>
                                            <select
                                                id="payments-month-part"
                                                className="h-9 min-w-[10rem] rounded-md border border-input bg-background px-3 text-sm"
                                                value={selectedMonthPart}
                                                onChange={(event) => {
                                                    navigatePaymentsMonth(
                                                        selectedYear,
                                                        event.target.value,
                                                    );
                                                }}
                                            >
                                                {calendarMonths.map((m) => (
                                                    <option
                                                        key={m.value}
                                                        value={m.value}
                                                    >
                                                        {m.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="grid gap-1">
                                            <Label htmlFor="payments-year">
                                                Año
                                            </Label>
                                            <select
                                                id="payments-year"
                                                className="h-9 min-w-[6rem] rounded-md border border-input bg-background px-3 text-sm"
                                                value={selectedYear}
                                                onChange={(event) => {
                                                    navigatePaymentsMonth(
                                                        event.target.value,
                                                        selectedMonthPart,
                                                    );
                                                }}
                                            >
                                                {calendarYears.map((y) => (
                                                    <option
                                                        key={y.value}
                                                        value={y.value}
                                                    >
                                                        {y.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Por defecto: mes y año actuales. La
                                        columna «En el mes» usa el periodo
                                        elegido; «Último pago» es el más
                                        reciente en general.
                                    </p>
                                </div>
                            </div>

                            <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                                <table className="w-full min-w-[880px] caption-bottom border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b border-sidebar-border/70">
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                Alumno
                                            </th>
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                Curso
                                            </th>
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                Correo
                                            </th>
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                Último pago
                                            </th>
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                En el mes
                                            </th>
                                            <th className="h-11 px-3 py-2 text-right align-middle font-medium text-muted-foreground">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="px-3 py-8 text-center text-muted-foreground"
                                                >
                                                    No hay inscripciones
                                                    activas.
                                                </td>
                                            </tr>
                                        ) : (
                                            rows.map((row) => (
                                                <tr
                                                    key={row.enrollment_id}
                                                    className="border-b border-sidebar-border/70 last:border-0"
                                                >
                                                    <td className="px-3 py-3 align-middle">
                                                        <p className="font-medium">
                                                            {row.student_name}
                                                        </p>
                                                        <Link
                                                            href={admin.students.show.url(
                                                                row.student_id,
                                                            )}
                                                            className="text-sm text-primary underline-offset-4 hover:underline"
                                                        >
                                                            Ver alumno
                                                        </Link>
                                                    </td>
                                                    <td className="px-3 py-3 align-middle">
                                                        {row.course_label}
                                                    </td>
                                                    <td className="max-w-[14rem] truncate px-3 py-3 align-middle">
                                                        {row.student_email}
                                                    </td>
                                                    <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                        {formatDateEs(
                                                            row.last_payment_at,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                        {formatDateEs(
                                                            row.last_payment_in_selected_month_at,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-3 text-right align-middle">
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="gap-1"
                                                                >
                                                                    <MoreHorizontal className="size-4" />
                                                                    Acciones
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        openPaymentModal(
                                                                            row,
                                                                        )
                                                                    }
                                                                >
                                                                    Registrar
                                                                    pago
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    ) : (
                        <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                            <p className="mb-3 text-sm text-muted-foreground">
                                Personas que dejaron sus datos antes de
                                formalizar la inscripción. Usa «Inscribir» para
                                crear la ficha de alumno.
                            </p>
                            <table className="w-full min-w-[640px] caption-bottom border-collapse text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70">
                                        <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                            Nombre
                                        </th>
                                        <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                            Correo
                                        </th>
                                        <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                            Teléfono
                                        </th>
                                        <th className="h-11 px-3 py-2 text-right align-middle font-medium text-muted-foreground">
                                            Acción
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {preRegistrations.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-3 py-8 text-center text-muted-foreground"
                                            >
                                                No hay preinscripciones.
                                            </td>
                                        </tr>
                                    ) : (
                                        preRegistrations.map((pr) => (
                                            <tr
                                                key={pr.id}
                                                className="border-b border-sidebar-border/70 last:border-0"
                                            >
                                                <td className="px-3 py-3 align-middle font-medium">
                                                    {pr.name}
                                                </td>
                                                <td className="max-w-[14rem] truncate px-3 py-3 align-middle">
                                                    {pr.email}
                                                </td>
                                                <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                    {pr.phone ?? '—'}
                                                </td>
                                                <td className="px-3 py-3 text-right align-middle">
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={admin.preRegistrations.enroll.create.url(
                                                                pr.id,
                                                            )}
                                                        >
                                                            Inscribir
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            <Dialog
                open={paymentModalOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closePaymentModal();
                    } else {
                        setPaymentModalOpen(true);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <form onSubmit={submitPayment}>
                        <DialogHeader>
                            <DialogTitle>Registrar pago</DialogTitle>
                            <DialogDescription>
                                Monto obligatorio. Para efectivo no hace falta
                                referencia ni archivo. Para transferencia u
                                otro, indica referencia o adjunta comprobante.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="pay-amount">Monto</Label>
                                <Input
                                    id="pay-amount"
                                    name="amount"
                                    type="number"
                                    inputMode="decimal"
                                    step="0.01"
                                    min="0"
                                    required
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData('amount', e.target.value)
                                    }
                                    disabled={form.processing}
                                />
                                <InputError message={form.errors.amount} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pay-method">Medio</Label>
                                <select
                                    id="pay-method"
                                    name="method"
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    value={form.data.method}
                                    onChange={(e) => {
                                        const v = e.target
                                            .value as PaymentMethod;
                                        form.setData('method', v);
                                        if (v === 'efectivo') {
                                            form.setData('reference', '');
                                            form.setData('receipt', null);
                                            if (receiptRef.current) {
                                                receiptRef.current.value = '';
                                            }
                                        }
                                    }}
                                    disabled={form.processing}
                                >
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">
                                        Transferencia
                                    </option>
                                    <option value="otro">Otro</option>
                                </select>
                                <InputError message={form.errors.method} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pay-reference">
                                    Referencia{' '}
                                    {cashMode ? (
                                        <span className="text-muted-foreground">
                                            (no aplica en efectivo)
                                        </span>
                                    ) : null}
                                </Label>
                                <Input
                                    id="pay-reference"
                                    name="reference"
                                    value={form.data.reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'reference',
                                            e.target.value,
                                        )
                                    }
                                    disabled={form.processing || cashMode}
                                    placeholder="Nº transferencia, etc."
                                />
                                <InputError message={form.errors.reference} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pay-receipt">
                                    Comprobante (imagen)
                                </Label>
                                <Input
                                    ref={receiptRef}
                                    id="pay-receipt"
                                    name="receipt"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    disabled={form.processing || cashMode}
                                    onChange={(e) =>
                                        form.setData(
                                            'receipt',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={form.errors.receipt} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pay-paid-at">
                                    Fecha / hora del pago
                                </Label>
                                <Input
                                    id="pay-paid-at"
                                    name="paid_at"
                                    type="datetime-local"
                                    value={form.data.paid_at}
                                    onChange={(e) =>
                                        form.setData('paid_at', e.target.value)
                                    }
                                    disabled={form.processing}
                                />
                                <InputError message={form.errors.paid_at} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pay-due-at">
                                    Vencimiento (opcional)
                                </Label>
                                <Input
                                    id="pay-due-at"
                                    name="due_at"
                                    type="datetime-local"
                                    value={form.data.due_at}
                                    onChange={(e) =>
                                        form.setData('due_at', e.target.value)
                                    }
                                    disabled={form.processing}
                                />
                                <InputError message={form.errors.due_at} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pay-notes">Notas</Label>
                                <textarea
                                    id="pay-notes"
                                    name="notes"
                                    rows={3}
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    disabled={form.processing}
                                    className="flex min-h-[72px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none"
                                />
                                <InputError message={form.errors.notes} />
                            </div>

                            <input
                                type="hidden"
                                name="student_id"
                                value={form.data.student_id || ''}
                            />
                            <input
                                type="hidden"
                                name="course_id"
                                value={form.data.course_id || ''}
                            />
                            <input
                                type="hidden"
                                name="return_month"
                                value={form.data.return_month}
                            />
                        </div>

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closePaymentModal}
                                disabled={form.processing}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Guardando…'
                                    : 'Guardar pago'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminPayments.layout = {
    breadcrumbs: [
        {
            title: 'Pagos',
            href: admin.payments.index.url(),
        },
    ],
};
