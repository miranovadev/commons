import { ActionIcon, Box, Text } from '@mantine/core';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-react';
import { Children, useLayoutEffect, useId, useRef, useState, type ReactNode } from 'react';
import { navigationState, navigationTarget } from '../navigation';

export function PropositionNavigator({ children, label, resetKey }: { children: ReactNode; label: string; resetKey?: string }) {
  const items = Children.toArray(children);
  const viewport = useRef<HTMLDivElement>(null);
  const id = useId();
  const [navigation, setNavigation] = useState({ overflow: false, previous: false, next: false });
  useLayoutEffect(() => {
    const element = viewport.current;
    if (!element) return;
    const update = () => setNavigation(navigationState(element.scrollLeft, element.clientWidth, element.scrollWidth));
    element.scrollLeft = 0;
    update();
    const observer = new ResizeObserver(update);
    observer.observe(element);
    for (const child of element.children) observer.observe(child);
    element.addEventListener('scroll', update, { passive: true });
    return () => { observer.disconnect(); element.removeEventListener('scroll', update); };
  }, [items.length, resetKey]);
  function move(direction: -1 | 1) {
    const element = viewport.current;
    if (!element) return;
    const left = element.getBoundingClientRect().left + element.clientLeft;
    const offsets = Array.from(element.children, child => child.getBoundingClientRect().left - left + element.scrollLeft);
    element.scrollTo({ left: navigationTarget(element.scrollLeft, element.clientWidth, offsets, direction),
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }
  return <Box className="proposition-navigator" data-overflow={navigation.overflow} data-count={items.length}>
    {navigation.overflow && <>
      <ActionIcon className="proposition-navigator__previous" variant="light" color="gray" radius="xl" size={28}
        aria-label={`Previous ${label}`} aria-controls={id} disabled={!navigation.previous} hidden={!navigation.previous} onClick={() => move(-1)}><IconChevronLeft size={18} /></ActionIcon>
      <ActionIcon className="proposition-navigator__next" variant="light" color="gray" radius="xl" size={28}
        aria-label={`Next ${label}`} aria-controls={id} disabled={!navigation.next} hidden={!navigation.next} onClick={() => move(1)}><IconChevronRight size={18} /></ActionIcon>
    </>}
    <Box ref={viewport} id={id} className="proposition-navigator__viewport" role="region" aria-label={`${label}, ${items.length} Proposition${items.length === 1 ? '' : 's'}`}
      tabIndex={navigation.overflow ? 0 : undefined} onKeyDown={event => {
        if (event.target !== event.currentTarget) return;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); move(event.key === 'ArrowLeft' ? -1 : 1); }
      }}>
      {items.map((item, index) => <Box className="proposition-navigator__item" key={(item as { key?: string }).key || index}>{item}</Box>)}
    </Box>
    {navigation.overflow && <Text className="proposition-navigator__hint" size="10px" c="dimmed">{items.length} Propositions · swipe or use arrows</Text>}
  </Box>;
}
