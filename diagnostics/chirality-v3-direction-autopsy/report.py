"""Render the preserved replay and persistence evidence without recalculating fields."""
import json,pathlib
p=pathlib.Path(__file__).parent
x=json.loads((p/'result-3-sparse_5.json').read_text())
d=json.loads((p/'direction-records.json').read_text())
out=['# Direction persistence lineage','', 'Post-action snapshots from the exact replay. `prefix:n` and `continuation:n` are zero-based. Local component numbers are not separately attenuated. The raw JSON also records every measurement/history SQL write, including intermediate writes within an action. Changes below omit identical repeat writes.','', '| Boundary | Proposition / producing Phase | Table | Previous O / AC directions | Current local O / direction | Current local AC / direction | Intervals | Maturity | Attenuated Chirality |','|---|---|---|---|---|---|---|---|---|']
for r in d['changes']:
 c=r['current']; old=r['previous'] or {}; obs=r['observation']
 out.append('| '+ ' | '.join(map(str,[r['stage'],r['proposition_id']+' / '+str(r['producing_phase']),r['table'],str(old.get('orientation_direction','none'))+' / '+str(old.get('attestation_cadence_direction','none')),str(c.get('orientation'))+' / '+str(c.get('orientation_direction')),str(c.get('attestation_cadence'))+' / '+str(c.get('attestation_cadence_direction')),obs['current_history']['intervals'],obs['cadence_status'],c.get('chirality')]))+' |')
(p/'DIRECTION-LINEAGE.md').write_text('\n'.join(out)+'\n')
out=['# Selected boundary trace','','No authored action added to the previous history. This replay stops after continuation action 33, before the already-recorded action 34 (Rest at fully grounded Bias). All fields use the original candidate and production services.','','| Boundary | Actor / Cycle / Phase | Gate | O / direction | Cadence / direction | Intervals | Maturity | Chirality | Correspondence | Closure | Tension | Charge | Vote / Receipt | Available actions | Coherence |','|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|']
rows=[('prefix:'+str(r['step']),r['actor'],r) for r in x['trace']]+[('continuation:'+str(r['step']),r['actor'],r['before']) for r in x['continuation']['actions']]+[('terminal',r['actor'],r) for r in x['final_states']]
for label,a,r in rows:
 if 'field' not in r: continue
 f=r['field']; l=r['lifecycle']; g=l['chirality_gate']; gr=l['grounding']['requirements'];
 out.append('| '+' | '.join(map(str,[label,f"A{a} / C{r['cycle']} / P{r['phase']}",r['gate_id'],str(f['orientation'])+' / '+str(g['orientation_direction']),str(f['attestation_cadence'])+' / '+str(g['attestation_cadence_direction']),f['current_history']['intervals'],f['cadence_status'],f['chirality'],f['correspondence'],f['closure'],f['tension'],f['charge'],str(gr['attestation'])+' / '+str(gr['receipt']),', '.join(l['available_actions']),g['reason'] or 'agrees']))+' |')
(p/'BOUNDARIES.md').write_text('\n'.join(out)+'\n')
