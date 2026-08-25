import { GripVertical } from 'lucide-react';
import { useRef, useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type SortableHandleProps = {
    onPointerDown: (event: React.PointerEvent) => void;
};

export function SortableHandle({
    label,
    ...props
}: SortableHandleProps & { label: string }) {
    return (
        <button
            type="button"
            className="inline-flex shrink-0 cursor-grab touch-none text-muted-foreground active:cursor-grabbing"
            aria-label={label}
            {...props}
        >
            <GripVertical className="size-5" />
        </button>
    );
}

function arrayMove<T>(items: T[], from: number, to: number): T[] {
    const next = [...items];
    const [item] = next.splice(from, 1);

    next.splice(to, 0, item);

    return next;
}

export default function SortableList<T extends { id: number }>({
    items,
    onChange,
    onReorder,
    disabled = false,
    className,
    renderItem,
}: {
    items: T[];
    onChange: (items: T[]) => void;
    onReorder: (orderedIds: number[]) => Promise<void>;
    disabled?: boolean;
    className?: string;
    renderItem: (
        item: T,
        handleProps: SortableHandleProps,
        isDragging: boolean,
    ) => ReactNode;
}) {
    const listRef = useRef<HTMLDivElement>(null);
    const itemsRef = useRef(items);
    const snapshotRef = useRef(items);
    const onChangeRef = useRef(onChange);
    const onReorderRef = useRef(onReorder);
    const draggingIdRef = useRef<number | null>(null);
    const [draggingId, setDraggingId] = useState<number | null>(null);

    itemsRef.current = items;
    onChangeRef.current = onChange;
    onReorderRef.current = onReorder;

    function beginDrag(id: number, event: React.PointerEvent): void {
        if (disabled || event.button !== 0) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        snapshotRef.current = itemsRef.current;
        draggingIdRef.current = id;
        setDraggingId(id);

        const previousCursor = document.body.style.cursor;
        const previousUserSelect = document.body.style.userSelect;
        document.body.style.cursor = 'grabbing';
        document.body.style.userSelect = 'none';

        let finished = false;

        function handlePointerMove(moveEvent: PointerEvent): void {
            const activeId = draggingIdRef.current;

            if (activeId === null || !listRef.current) {
                return;
            }

            const nodes =
                listRef.current.querySelectorAll<HTMLElement>('[data-sortable-id]');
            const y = moveEvent.clientY;

            for (const node of nodes) {
                const rect = node.getBoundingClientRect();

                if (y < rect.top || y > rect.bottom) {
                    continue;
                }

                const overId = Number(node.dataset.sortableId);

                if (Number.isNaN(overId) || overId === activeId) {
                    return;
                }

                const current = itemsRef.current;
                const from = current.findIndex((item) => item.id === activeId);
                const to = current.findIndex((item) => item.id === overId);

                if (from < 0 || to < 0 || from === to) {
                    return;
                }

                onChangeRef.current(arrayMove(current, from, to));

                return;
            }
        }

        function finishDrag(): void {
            if (finished) {
                return;
            }

            finished = true;
            document.removeEventListener('pointermove', handlePointerMove);
            document.removeEventListener('pointerup', finishDrag);
            document.removeEventListener('pointercancel', finishDrag);
            document.body.style.cursor = previousCursor;
            document.body.style.userSelect = previousUserSelect;
            draggingIdRef.current = null;
            setDraggingId(null);

            const nextIds = itemsRef.current.map((item) => item.id);
            const previousIds = snapshotRef.current.map((item) => item.id);
            const unchanged = nextIds.every(
                (id, index) => id === previousIds[index],
            );

            if (unchanged) {
                return;
            }

            void onReorderRef.current(nextIds).catch(() => {
                onChangeRef.current(snapshotRef.current);
            });
        }

        document.addEventListener('pointermove', handlePointerMove);
        document.addEventListener('pointerup', finishDrag);
        document.addEventListener('pointercancel', finishDrag);
    }

    return (
        <div ref={listRef} className={cn('flex flex-col gap-2', className)}>
            {items.map((item) =>
                renderItem(
                    item,
                    {
                        onPointerDown: (event) => beginDrag(item.id, event),
                    },
                    item.id === draggingId,
                ),
            )}
        </div>
    );
}
