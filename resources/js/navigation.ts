export function navigationState(scrollLeft: number, clientWidth: number, scrollWidth: number) {
  const max = Math.max(0, scrollWidth - clientWidth);
  return { overflow: max > 2, previous: scrollLeft > 2, next: scrollLeft < max - 2 };
}
export function navigationTarget(scrollLeft: number, width: number, offsets: number[], direction: -1 | 1) {
  const desired = scrollLeft + direction * width;
  const candidates = offsets.filter(offset => direction === 1 ? offset > scrollLeft + 2 : offset < scrollLeft - 2);
  return candidates.length ? candidates.reduce((best, offset) => Math.abs(offset - desired) < Math.abs(best - desired) ? offset : best) : Math.max(0, desired);
}
