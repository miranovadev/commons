/** Stable palette selection; unrelated to domain/register or Attestation semantics. */
const colors = ['indigo', 'grape', 'blue', 'cyan', 'pink', 'lime', 'orange', 'violet', 'teal'] as const;
export function participantColor(callsign: string): typeof colors[number] {
  let hash = 0;
  for (const character of callsign.trim().normalize('NFKC').toLowerCase()) hash = (Math.imul(hash, 31) + character.codePointAt(0)!) >>> 0;
  return colors[hash % colors.length];
}
