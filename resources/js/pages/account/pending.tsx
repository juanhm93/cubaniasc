import { Head, Link } from '@inertiajs/react';
import { logout } from '@/routes';

export default function AccountPending() {
    return (
        <>
            <Head title="Cuenta pendiente" />

            <div className="space-y-4 text-center text-sm leading-relaxed text-muted-foreground">
                <p className="text-base font-medium text-foreground">
                    Tu cuenta se creó correctamente.
                </p>
                <p>
                    Por ahora tu usuario está en estado{' '}
                    <span className="font-medium text-foreground">pendiente</span>
                    : necesita ser aprobado por el propietario o un administrador
                    de la academia antes de que puedas usar el resto de la
                    plataforma.
                </p>
                <p>
                    Cuando tu cuenta sea activada, podrás iniciar sesión y
                    acceder al panel, cursos y el resto de herramientas. Si
                    llevas tiempo esperando, escribe a la administración o al
                    contacto que te invitó a registrarte.
                </p>

                <Link
                    href={logout()}
                    method="post"
                    as="button"
                    className="inline-block pt-2 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Cerrar sesión
                </Link>
            </div>
        </>
    );
}

AccountPending.layout = {
    title: 'Cuenta pendiente',
    description:
        'Tu registro está en revisión. Te avisaremos cuando puedas acceder.',
};
