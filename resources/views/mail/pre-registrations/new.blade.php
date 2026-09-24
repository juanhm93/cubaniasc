@component('mail::message')
# 🎉 ¡Tenemos una nueva preinscripción!

Alguien más quiere bailar con nosotros. **{{ $preRegistration->name }}** acaba de completar el formulario de preinscripción.

@component('mail::panel')
**Nombre:** {{ $preRegistration->name }}

**Correo:** {{ $preRegistration->email }}

**Teléfono:** {{ $preRegistration->phone ?? 'No indicado' }}

**País:** {{ $countryLabel ?? 'No indicado' }}

**Mensaje:** {{ $preRegistration->message ?? 'Sin mensaje' }}

**Autorizó el tratamiento de sus datos:** {{ $preRegistration->agree ? 'Sí' : 'No' }}

**Fecha de envío:** {{ optional($preRegistration->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
@endcomponent

Entra a la plataforma para revisar los datos y convertir la preinscripción en estudiante.

@component('mail::button', ['url' => $actionUrl])
Ver la preinscripción
@endcomponent

¡A seguir sumando gente a la pista! 💃🕺

Gracias,<br>
{{ config('app.name') }}
@endcomponent
