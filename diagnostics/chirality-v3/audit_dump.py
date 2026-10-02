#!/usr/bin/env python3
"""Read PostgreSQL COPY text without executing SQL; report ordering evidence only."""
import sys, re, json, collections
from pathlib import Path

def read_dump(path):
    tables = {}
    with open(path) as f:
        for line in f:
            m = re.match(r'COPY public\.(\w+) \(([^)]+)\) FROM stdin;', line)
            if not m:
                continue
            name, cols = m[1], m[2].split(', ')
            rows = []
            for line in f:
                if line.rstrip('\n') == r'\.':
                    break
                vals = line.rstrip('\n').split('\t')
                rows.append(dict(zip(cols, [None if x == r'\N' else x for x in vals])))
            tables[name] = rows
    return tables

if __name__ == '__main__':
    t = read_dump(sys.argv[1])
    idx = {name: {x['id']: x for x in t.get(name, [])} for name in ('participants','cycles','phases','propositions','substrates')}
    def cycle_of_phase(p): return idx['cycles'][idx['phases'][p]['cycle_id']]
    def substrate_of_cycle(c): return idx['participants'][c['participant_id']]['substrate_id']
    ledgers = {(x['ledgerable_type'],x['ledgerable_id']): x for x in t['substrate_ledger_entries']}
    ledger_by_id = {x['ledgerable_id']:x for x in t['substrate_ledger_entries']}
    output = {'ledger_types': dict(collections.Counter(x['ledgerable_type'].replace('\\\\', '\\') for x in t['substrate_ledger_entries'])), 'attestation_timestamp_vs_ledger_mismatches': sum(a['created_at'] != ledger_by_id[a['id']]['recorded_at'][:19] for a in t['attestations']), 'substrates': []}
    for sub in t['substrates']:
        sid = sub['id']
        cycles = [c for c in t['cycles'] if substrate_of_cycle(c)==sid]
        acts = [a for a in t['attestations'] if substrate_of_cycle(cycle_of_phase(a['phase_id']))==sid]
        ties=[]
        for a in acts:
            ac=cycle_of_phase(a['phase_id']); targetc=cycle_of_phase(idx['propositions'][a['proposition_id']]['phase_id'])
            for c in cycles:
                if c['id']!=ac['id'] and a['created_at']<=c['created_at']<=ledger_by_id[a['id']]['recorded_at'][:19] and c['context_horizon_id']==targetc['context_horizon_id']:
                    ties.append({'attestation_id':a['id'],'attestation_ledger_sequence':ledger_by_id.get(a['id'],{}).get('sequence'),'attestation_acting_cycle':ac['id'],'attestation_target_producing_cycle':targetc['id'],'attestation_target_entry':targetc['entry_committal_id'],'cycle_id':c['id'],'cycle_entry':c['entry_committal_id'],'same_region':c['entry_committal_id']==targetc['entry_committal_id'],'timestamp':c['created_at']})
        output['substrates'].append({'id':sid,'created_at':sub.get('created_at'),'cycles':len(cycles),'attestations':len(acts),'attestation_timestamp_vs_ledger_mismatches':sum(a['created_at']!=ledger_by_id[a['id']]['recorded_at'][:19] for a in acts), 'attestations_without_ledger':sum(a['id'] not in ledger_by_id for a in acts),'cycles_with_ledger':sum(c['id'] in ledger_by_id for c in cycles),'cross_cycle_same_horizon_act_recording_window_overlaps':len(ties),'same_region_ties':sum(x['same_region'] for x in ties),'examples':ties[:5]})
    print(json.dumps(output,indent=2))
