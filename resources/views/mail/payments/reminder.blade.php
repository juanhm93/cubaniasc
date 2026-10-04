@component('mail::message')
@if ($isLastDay)
# ⏰ ¡Mañana empieza {{ $periodLabel }}!

Hola {{ $studentName }}, este es el último recordatorio: mañana arranca un nuevo mes de clases y todavía no vemos tu pago.
@else
# 🗓️ Ya puedes pagar {{ $periodLabel }}

Hola {{ $studentName }}, se acerca fin de mes y abrimos el periodo de pago de **{{ $periodLabel }}**. Pagar a tiempo nos ayuda a organizar las clases y a que no te pierdas ni un paso.
@endif

@component('mail::table')
| Curso | Monto |
|:------|------:|
@foreach ($courses as $course)
| {{ $course['label'] }} | {{ $course['price'] }} |
@endforeach
@if (count($courses) > 1)
| **Total** | **{{ $total }}** |
@endif
@endcomponent

Puedes pagar en la academia o por transferencia. Si pagas por transferencia, envíanos la referencia o el comprobante para registrarlo.

Si ya pagaste, ¡gracias! Ignora este mensaje: puede que aún no lo hayamos registrado.

¡Nos vemos en la pista! 💃🕺

Gracias,<br>
{{ config('app.name') }}
@endcomponent
