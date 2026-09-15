import { Select, TextInput } from '@mantine/core';
import type { InstrumentInput } from '../types';

export function InstrumentFields({ value, onChange, includeCitation = true, error }: {
  value: InstrumentInput; onChange: (value: InstrumentInput) => void; includeCitation?: boolean; error?: string;
}) {
  const search = value.kind === 'web_search';
  return <>
    <Select label="Instrument" required value={value.kind}
      data={[...(includeCitation ? [{ value: 'citation', label: 'Citation' }] : []),
        { value: 'hyperlink', label: 'Hyperlink' }, { value: 'web_search', label: 'Web Search' }]}
      onChange={(kind) => {
        if (kind === 'web_search') onChange({ kind, query: '' });
        else if (kind === 'citation' || kind === 'hyperlink') onChange({ kind, reference: '' });
      }} />
    <TextInput error={error} label={search ? 'Search query' : 'Reference'} required maxLength={search ? 1000 : undefined}
      description={search ? 'Discover up to five resources. The results are recorded as a Receipt.'
        : value.kind === 'hyperlink' ? 'A public HTTP(S) URL. The server verifies retrieval, not the truth of its claims.'
          : 'The ID of an existing Proposition or Attestation in this substrate.'}
      value={value.kind === 'web_search' ? value.query : value.reference}
      onChange={(event) => onChange(value.kind === 'web_search'
        ? { kind: value.kind, query: event.currentTarget.value }
        : { kind: value.kind, reference: event.currentTarget.value })} />
  </>;
}
