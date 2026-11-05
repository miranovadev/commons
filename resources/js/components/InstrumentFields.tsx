import { NumberInput, Select, TextInput } from '@mantine/core';
import type { InstrumentInput } from '../types';

export function InstrumentFields({ value, onChange, kinds, error, replayIds = [] }: {
  value: InstrumentInput; onChange: (value: InstrumentInput) => void; kinds: string[]; error?: string; replayIds?: string[];
}) {
  const replay = 'replay_receipt_id' in value;
  const search = !replay && (value.kind === 'web_search' || value.kind === 'proposition_search');
  return <>
    <Select label="Instrument" required value={replay ? 'replay' : value.kind}
      data={[{ value: 'citation', label: 'Citation' },
        { value: 'hyperlink', label: 'Hyperlink' }, { value: 'load_file', label: 'Load File · Download' }, { value: 'web_search', label: 'Web Search' },
        { value: 'proposition_search', label: 'Proposition Search' }, { value: 'replay', label: 'Replay stored invocation' }].filter(item => item.value === 'replay' ? replayIds.length > 0 : kinds.includes(item.value))}
      onChange={(kind) => {
        if (kind === 'web_search') onChange({ kind, query: '' });
        else if (kind === 'proposition_search') onChange({ kind });
        else if (kind === 'load_file') onChange({ kind, method: 'download', reference: '' });
        else if (kind === 'citation' || kind === 'hyperlink') onChange({ kind, reference: '' });
        else if (kind === 'replay' && replayIds.length) onChange({ replay_receipt_id: replayIds[0] });
      }} />
    {replay ? <Select label="Replay Receipt" required data={replayIds} value={replayIds.includes(value.replay_receipt_id) ? value.replay_receipt_id : null}
      description="Repeat this Proposition's stored invocation. Creates new evidence without loading it."
      onChange={id => { if (id && replayIds.includes(id)) onChange({ replay_receipt_id: id }); }} />
      : <TextInput error={error} label={search ? 'Search query' : value.kind === 'load_file' ? 'Download URL' : value.kind === 'citation' ? 'Citation subject ULID' : 'Reference'} required={value.kind !== 'proposition_search'} maxLength={search ? 1000 : undefined}
        description={value.kind === 'proposition_search' ? 'Search historical Propositions. Leave the query empty to browse.' : search ? 'Discover up to five resources. The results are recorded as a Receipt.'
          : value.kind === 'load_file' ? 'Download one public HTTP(S) resource into a private immutable Artifact. Its Receipt is not loadable; supported files can be examined through Preparation.'
          : value.kind === 'hyperlink' ? 'A public HTTP(S) URL. The server verifies retrieval, not the truth of its claims.'
            : 'Use the ULID of an existing Proposition or Attestation in this substrate. For an external URL, choose Hyperlink.'}
        value={'reference' in value ? value.reference : value.query ?? ''}
        onChange={(event) => onChange(value.kind === 'proposition_search'
          ? { ...value, query: event.currentTarget.value || undefined }
          : value.kind === 'web_search' ? { kind: value.kind, query: event.currentTarget.value }
          : { ...value, reference: event.currentTarget.value })} />}
    {!replay && value.kind === 'proposition_search' && <NumberInput label="Result limit" min={1} max={10} allowDecimal={false}
      placeholder="5" value={value.limit ?? ''} onChange={limit => onChange({ ...value, limit: typeof limit === 'number' ? limit : undefined })} />}
  </>;
}
