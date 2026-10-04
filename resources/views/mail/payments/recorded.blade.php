@component('mail::message')
# 💸 Nuevo pago registrado

Se registró un pago de **{{ $studentName }}** para **{{ $courseLabel }}**.

@component('mail::panel')
**Alumno:** {{ $studentName }}

**Curso:** {{ $courseLabel }}

**Monto:** {{ $amount }}

**Mes que cubre:** {{ $periodLabel ?? 'No indicado' }}

**Método:** {{ $method }}

**Referencia:** {{ $payment->reference ?? 'Sin referencia' }}

**Comprobante:** {{ $payment->receipt_path ? 'Adjunto en la plataforma' : 'Sin comprobante' }}

**Fecha de pago:** {{ $paidAt }}

@if ($actorName)
**Registrado por:** {{ $actorName }}
@endif
@endcomponent

@component('mail::button', ['url' => $actionUrl])
Ver pagos del mes
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
