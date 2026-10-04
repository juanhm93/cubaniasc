@component('mail::message')
# 👤 Nueva cuenta esperando aprobación

**{{ $user->name }}** creó una cuenta en la plataforma y no podrá entrar hasta que la actives.

@component('mail::panel')
**Nombre:** {{ $user->name }}

**Correo:** {{ $user->email }}

**Fecha de registro:** {{ $registeredAt }}
@endcomponent

Revisa que sea alguien del equipo, asígnale un rol y activa la cuenta.

@component('mail::button', ['url' => $actionUrl])
Revisar usuarios
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
