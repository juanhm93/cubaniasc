@component('mail::message')
# ✅ ¡Pago recibido, {{ $studentName }}!

Gracias por tu pago. Aquí tienes el detalle para tus registros.

@component('mail::panel')
**Curso:** {{ $courseLabel }}

**Monto:** {{ $amount }}

**Mes que cubre:** {{ $periodLabel ?? 'No indicado' }}

**Método:** {{ $method }}

@if ($payment->reference)
**Referencia:** {{ $payment->reference }}

@endif
**Fecha de pago:** {{ $paidAt }}
@endcomponent

@if ($isPartial)
Este pago quedó registrado como **abono parcial**. Si tienes dudas sobre el saldo pendiente, escríbenos.
@endif

Mientras llega la próxima clase, sigue practicando en tu panel de repaso. 💃

@component('mail::button', ['url' => $reviewUrl])
Ir a mi panel de repaso
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
