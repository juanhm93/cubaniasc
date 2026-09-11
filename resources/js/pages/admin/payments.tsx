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
import { useAbilities } from '@/hooks/use-abilities';
import { useTranslation } from '@/i18n/use-translation';
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

function formatDateEs(iso: string | null, emptyLabel: string): string {
    if (!iso) {
        return emptyLabel;
    }

    try {
        return new Date(iso).toLocaleDateString('es', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return emptyLabel;
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
    const { t } = useTranslation();
    const abilities = useAbilities();
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
                toast.success(t('admin.payments.paymentRegistered'));
                closePaymentModal();
            },
            onError: () => {
                toast.error(t('admin.payments.paymentRegisterFailed'));
            },
        });
    };

    const cashMode = form.data.method === 'efectivo';
    const emptyDateLabel = t('common.emDash');

    return (
        <>
            <Head title={t('admin.payments.title')} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="relative flex min-h-[100vh] flex-1 flex-col gap-4 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 md:min-h-min dark:border-sidebar-border">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex flex-col gap-2">
                            <h1 className="text-2xl font-semibold">
                                {t('admin.payments.title')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.payments.description')}
                            </p>
                        </div>
                        {abilities.students ? (
                            <Button asChild>
                                <Link href={admin.payments.enroll.create.url()}>
                                    {t('admin.payments.enrollStudent')}
                                </Link>
                            </Button>
                        ) : null}
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
                            {t('admin.payments.tabStudents')}
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
                            {t('admin.payments.tabPreRegistered')}
                        </button>
                    </div>

                    {tab === 'alumnos' ? (
                        <>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                                <div className="grid gap-2">
                                    <span className="text-sm font-medium">
                                        {t('admin.payments.referenceMonth')}
                                    </span>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <div className="grid gap-1">
                                            <Label htmlFor="payments-month-part">
                                                {t('common.month')}
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
                                                {t('common.year')}
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
                                        {t('admin.payments.referenceMonthHint')}
                                    </p>
                                </div>
                            </div>

                            <div className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                                <table className="w-full min-w-[880px] caption-bottom border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b border-sidebar-border/70">
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                {t(
                                                    'admin.students.studentLabel',
                                                )}
                                            </th>
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                {t('common.course')}
                                            </th>
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                {t('common.email')}
                                            </th>
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                {t(
                                                    'admin.payments.lastPayment',
                                                )}
                                            </th>
                                            <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                                {t('admin.payments.inMonth')}
                                            </th>
                                            <th className="h-11 px-3 py-2 text-right align-middle font-medium text-muted-foreground">
                                                {t('common.actions')}
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
                                                    {t(
                                                        'admin.payments.noActiveEnrollments',
                                                    )}
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
                                                            {t(
                                                                'admin.students.viewStudent',
                                                            )}
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
                                                            emptyDateLabel,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-3 align-middle whitespace-nowrap">
                                                        {formatDateEs(
                                                            row.last_payment_in_selected_month_at,
                                                            emptyDateLabel,
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
                                                                    {t(
                                                                        'common.actions',
                                                                    )}
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
                                                                    {t(
                                                                        'admin.payments.registerPayment',
                                                                    )}
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
                                {t('admin.payments.preRegisteredDescription')}
                            </p>
                            <table className="w-full min-w-[640px] caption-bottom border-collapse text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/70">
                                        <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                            {t('common.name')}
                                        </th>
                                        <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                            {t('common.email')}
                                        </th>
                                        <th className="h-11 px-3 py-2 text-left align-middle font-medium text-muted-foreground">
                                            {t('common.phone')}
                                        </th>
                                        <th className="h-11 px-3 py-2 text-right align-middle font-medium text-muted-foreground">
                                            {t('common.action')}
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
                                                {t(
                                                    'admin.payments.noPreRegistrations',
                                                )}
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
                                                    {pr.phone ??
                                                        t('common.emDash')}
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
                                                            {t(
                                                                'admin.payments.enroll',
                                                            )}
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
                            <DialogTitle>
                                {t('admin.payments.registerPaymentTitle')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('admin.payments.registerPaymentDescription')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="pay-amount">
                                    {t('common.amount')}
                                </Label>
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
                                <Label htmlFor="pay-method">
                                    {t('admin.payments.method')}
                                </Label>
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
                                    <option value="efectivo">
                                        {t('admin.paymentMethods.cash')}
                                    </option>
                                    <option value="transferencia">
                                        {t('admin.paymentMethods.transfer')}
                                    </option>
                                    <option value="otro">
                                        {t('admin.paymentMethods.other')}
                                    </option>
                                </select>
                                <InputError message={form.errors.method} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pay-reference">
                                    {t('admin.payments.reference')}{' '}
                                    {cashMode ? (
                                        <span className="text-muted-foreground">
                                            {t(
                                                'admin.payments.referenceNotApplicableCash',
                                            )}
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
                                    placeholder={t(
                                        'admin.payments.referencePlaceholder',
                                    )}
                                />
                                <InputError message={form.errors.reference} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pay-receipt">
                                    {t('admin.payments.receiptImage')}
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
                                    {t('admin.payments.paidAt')}
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
                                    {t('admin.payments.dueAtOptional')}
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
                                <Label htmlFor="pay-notes">
                                    {t('common.notes')}
                                </Label>
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
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? t('common.saving')
                                    : t('admin.payments.savePayment')}
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
            title: 'navigation.payments',
            href: admin.payments.index.url(),
        },
    ],
};
