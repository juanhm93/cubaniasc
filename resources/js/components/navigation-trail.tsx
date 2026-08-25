import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

export function parentTrailItem(
    items: BreadcrumbItem[],
): BreadcrumbItem | null {
    if (items.length < 2) {
        return null;
    }

    return items[items.length - 2] ?? null;
}

export default function NavigationTrail({
    items,
    className,
}: {
    items: BreadcrumbItem[];
    className?: string;
}) {
    const parent = parentTrailItem(items);

    if (items.length === 0) {
        return null;
    }

    return (
        <div className={cn('flex items-center gap-1', className)}>
            {parent ? (
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 shrink-0"
                    asChild
                >
                    <Link
                        href={parent.href}
                        aria-label={`Volver a ${parent.title}`}
                    >
                        <ArrowLeft />
                    </Link>
                </Button>
            ) : null}
            <Breadcrumbs breadcrumbs={items} />
        </div>
    );
}
