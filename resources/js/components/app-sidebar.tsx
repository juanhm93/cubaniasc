import { Link } from '@inertiajs/react';
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
import { useAbilities } from '@/hooks/use-abilities';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { index as contentIndex } from '@/routes/content';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const abilities = useAbilities();

    const footerNavItems: NavItem[] = [
        {
            title: 'common.repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'common.documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    const mainNavItems: NavItem[] = [
        {
            title: 'navigation.dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(abilities.content
            ? [
                  {
                      title: 'Contenido',
                      href: contentIndex(),
                      icon: BookOpen,
                  },
              ]
            : []),
        ...(abilities.payments
            ? [
                  {
                      title: 'navigation.payments',
                      href: admin.payments.index.url(),
                      icon: CreditCard,
                  },
              ]
            : []),
        ...(abilities.courses
            ? [
                  {
                      title: 'navigation.courses',
                      href: admin.courses.index.url(),
                      icon: GraduationCap,
                  },
              ]
            : []),
        ...(abilities.oneTimeSessions
            ? [
                  {
                      title: 'navigation.oneTimeSessions',
                      href: admin.oneTimeSessions.index.url(),
                      icon: GraduationCap,
                  },
              ]
            : []),
        ...(abilities.students
            ? [
                  {
                      title: 'navigation.students',
                      href: admin.students.index.url(),
                      icon: School,
                  },
              ]
            : []),
        ...(abilities.adminUsers
            ? [
                  {
                      title: 'navigation.adminUsers',
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
