import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CreditCard,
    FolderGit2,
    GraduationCap,
    LayoutGrid,
    School,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { index as contentIndex } from '@/routes/content';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props as {
        auth?: {
            user?: {
                role?: {
                    slug?: string;
                } | null;
            } | null;
        };
    };

    const isAdmin = auth?.user?.role?.slug === 'admin';
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(isAdmin
            ? [
                  {
                      title: 'Contenido',
                      href: contentIndex(),
                      icon: BookOpen,
                  },
                  {
                      title: 'Pagos',
                      href: admin.payments.index.url(),
                      icon: CreditCard,
                  },
                  {
                      title: 'Cursos',
                      href: admin.courses.index.url(),
                      icon: GraduationCap,
                  },
                  {
                      title: 'Clases especiales',
                      href: admin.oneTimeSessions.index.url(),
                      icon: GraduationCap,
                  },
                  {
                      title: 'Alumnos',
                      href: admin.students.index.url(),
                      icon: School,
                  },
                  {
                      title: 'Admin users',
                      href: '/admin/users',
                      icon: Users,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
