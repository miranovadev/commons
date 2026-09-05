export function CommonsLogo({ size = 34, monochrome = false }) {
  const top = monochrome ? 'currentColor' : '#748ffc';
  const middle = monochrome ? 'currentColor' : '#4263eb';
  const bottom = monochrome ? 'currentColor' : '#243574';

  return (
    <svg width={size} height={size} viewBox="0 0 108 108" role="img" aria-label="The Commons">
      <g fill="none" strokeLinecap="round" strokeWidth="12">
        <path stroke={top} d="M45 14h18M25 27h18M67 27h18M18 40h18" />
        <path stroke={middle} d="M14 54h18" />
        <path stroke={bottom} d="M18 67h18M25 80h18M67 80h18M45 93h18" />
      </g>
    </svg>
  );
}
