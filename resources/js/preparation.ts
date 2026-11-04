import type { RegexResultSet } from './types';

export function describeRegexResults(result: RegexResultSet): string {
  const total = result.total_matches ?? 'unknown (enumeration ceiling reached)';
  return 'Total matches: ' + total + ' · Included: ' + result.included_matches
    + (result.byte_limited ? ' · Result byte ceiling reached' : '');
}
