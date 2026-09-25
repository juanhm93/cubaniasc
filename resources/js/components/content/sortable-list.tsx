import { GripVertical } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
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

function applyGrabbingCursor(): () => void {
    const body = document.body;
    const previousCursor = body.style.getPropertyValue('cursor');
    const previousUserSelect = body.style.getPropertyValue('user-select');

    body.style.setProperty('cursor', 'grabbing');
    body.style.setProperty('user-select', 'none');

    return () => {
        body.style.setProperty('cursor', previousCursor);
        body.style.setProperty('user-select', previousUserSelect);
    };
}

function SortableItem<T extends { id: number }>({
    item,
    isDragging,
    disabled,
    onBeginDrag,
    renderItem,
}: {
    item: T;
    isDragging: boolean;
    disabled: boolean;
    onBeginDrag: (id: number, event: React.PointerEvent) => void;
    renderItem: (
        item: T,
        handleProps: SortableHandleProps,
        isDragging: boolean,
    ) => ReactNode;
}) {
    return renderItem(
        item,
        {
            onPointerDown: (event) => {
                if (disabled) {
                    return;
                }

                onBeginDrag(item.id, event);
            },
        },
        isDragging,
    );
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

    useLayoutEffect(() => {
        itemsRef.current = items;
        onChangeRef.current = onChange;
        onReorderRef.current = onReorder;
    });

    function beginDrag(id: number, event: React.PointerEvent): void {
        if (event.button !== 0) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        snapshotRef.current = itemsRef.current;
        draggingIdRef.current = id;
        setDraggingId(id);

        const restoreCursor = applyGrabbingCursor();
        let finished = false;

        function handlePointerMove(moveEvent: PointerEvent): void {
            const activeId = draggingIdRef.current;

            if (activeId === null || !listRef.current) {
                return;
            }

            const nodes =
                listRef.current.querySelectorAll<HTMLElement>(
                    '[data-sortable-id]',
                );
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
                const from = current.findIndex(
                    (entry) => entry.id === activeId,
                );
                const to = current.findIndex((entry) => entry.id === overId);

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
            restoreCursor();
            draggingIdRef.current = null;
            setDraggingId(null);

            const nextIds = itemsRef.current.map((entry) => entry.id);
            const previousIds = snapshotRef.current.map((entry) => entry.id);
            const unchanged = nextIds.every(
                (entryId, index) => entryId === previousIds[index],
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
            {items.map((item) => (
                <SortableItem
                    key={item.id}
                    item={item}
                    isDragging={item.id === draggingId}
                    disabled={disabled}
                    onBeginDrag={beginDrag}
                    renderItem={renderItem}
                />
            ))}
        </div>
    );
}
