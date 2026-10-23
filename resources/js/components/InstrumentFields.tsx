import { Select, TextInput } from '@mantine/core';
import type { InstrumentInput } from '../types';

export function InstrumentFields({ value, onChange, includeCitation = true, error, replayIds = [] }: {
  value: InstrumentInput; onChange: (value: InstrumentInput) => void; includeCitation?: boolean; error?: string; replayIds?: string[];
}) {
  const replay = 'replay_receipt_id' in value;
  const search = !replay && value.kind === 'web_search';
  return <>
    <Select label="Instrument" required value={replay ? 'replay' : value.kind}
      data={[...(includeCitation ? [{ value: 'citation', label: 'Citation' }] : []),
        { value: 'hyperlink', label: 'Hyperlink' }, { value: 'load_file', label: 'Load File · Download' }, { value: 'web_search', label: 'Web Search' },
        ...(replayIds.length ? [{ value: 'replay', label: 'Replay stored invocation' }] : [])]}
      onChange={(kind) => {
        if (kind === 'web_search') onChange({ kind, query: '' });
        else if (kind === 'load_file') onChange({ kind, method: 'download', reference: '' });
        else if (kind === 'citation' || kind === 'hyperlink') onChange({ kind, reference: '' });
        else if (kind === 'replay' && replayIds.length) onChange({ replay_receipt_id: replayIds[0] });
      }} />
    {replay ? <Select label="Replay Receipt" required data={replayIds} value={replayIds.includes(value.replay_receipt_id) ? value.replay_receipt_id : null}
      description="Repeat this Proposition's stored invocation. Creates new evidence without loading it."
      onChange={id => { if (id && replayIds.includes(id)) onChange({ replay_receipt_id: id }); }} />
      : <TextInput error={error} label={search ? 'Search query' : value.kind === 'load_file' ? 'Download URL' : 'Reference'} required maxLength={search ? 1000 : undefined}
        description={search ? 'Discover up to five resources. The results are recorded as a Receipt.'
          : value.kind === 'load_file' ? 'Download one public HTTP(S) resource into a private immutable Artifact. Its Receipt is not loadable; supported files can be examined through Preparation.'
          : value.kind === 'hyperlink' ? 'A public HTTP(S) URL. The server verifies retrieval, not the truth of its claims.'
            : 'The ID of an existing Proposition or Attestation in this substrate.'}
        value={value.kind === 'web_search' ? value.query : value.reference}
        onChange={(event) => onChange(value.kind === 'web_search'
          ? { kind: value.kind, query: event.currentTarget.value }
          : { ...value, reference: event.currentTarget.value })} />}
  </>;
}
