"""Render captured production and candidate observations; no field formula is reproduced."""
import json
from pathlib import Path
p=Path(__file__).parent
a=json.loads((p/'hard-edge.json').read_text())
b=a['source'];c=a['recorded_commit'];candidate=a['candidate_commit'];child=a.get('child')
names=dict(zip(sorted(z['id'] for z in b['parts']),['Origin','D1','P1','Q1','S1','D2','P2','Q2','S2','R','B']))
names[c['id']]='C1'
if child:names[child['id']]='E1'
def n(x):return '—' if x is None else f'{x:.7f}'
def name(i):return names.get(i,i)
def table(h,rows):return '\n'.join(['| '+' | '.join(h)+' |','| '+' | '.join(['---']*len(h))+' |']+['| '+' | '.join(str(v).replace('|','/').replace('\n',' ') for v in row)+' |' for row in rows])+'\n'
cr={z['id']:z for z in c['parts']}
rows=[]
for z in b['parts']:
 t=cr[z['id']]
 rows.append([name(z['id']),n(z['raw_chirality']),z['distance'],n(z['weight']),n(z['normalized_chirality']),n(t['raw_chirality']),t['distance'],n(t['weight']),n(t['normalized_chirality'])])
head=cr[c['id']]
rows.append(['C1 new head','—','—','—','—',n(head['raw_chirality']),head['distance'],n(head['weight']),n(head['normalized_chirality'])])
(p/'CHIRALITY-AUDIT.md').write_text('# Chirality across the hard edge\n\nThe comparator uses the preserved expressive candidate with the existing production Commit measurement path. Each ancestor raw value is reweighted at a greater distance; B’s stored aggregate is not an input to that rewalk.\n\n'+table(['Member','Raw χ at B','Distance B','Weight B','Normalized χ at B','Raw χ at C1','Distance C1','Weight C1','Normalized χ at C1'],rows)+f"\nB denominator mass: {b['mass']:.12f}; C1 denominator mass: {c['mass']:.12f}.\n\nCandidate C1 preserves B’s resolved Chirality {n(candidate['chirality'])} directly. It has no new local Orientation/Cadence calculation and no new lineage sum at the Commit observation.\n")
rows=[]
for z in b['parts']:
 t=cr[z['id']]
 rows.append([name(z['id']),n(z['shape_topology']),n(z['cardinal_position']),n(z['raw_correspondence']),n(z['normalized_correspondence']),n(t['shape_topology']),n(t['cardinal_position']),n(t['raw_correspondence']),n(t['normalized_correspondence'])])
rows.append(['C1 new head','—','—','—','—',n(head['shape_topology']),n(head['cardinal_position']),n(head['raw_correspondence']),n(head['normalized_correspondence'])])
(p/'CORRESPONDENCE-AUDIT.md').write_text('# Correspondence geometry across Commit\n\nRaw component values come from the real calculator at each boundary. Distances and weights are in CHIRALITY-AUDIT.md; both observables use the same lineage traversal and denominator.\n\n'+table(['Member','Shape at B','Cardinal at B','Raw C at B','Normalized C at B','Shape at C1','Cardinal at C1','Raw C at C1','Normalized C at C1'],rows)+f"\nResolved B C = {n(b['resolved']['correspondence'])}; recorded C1 C = {n(c['resolved']['correspondence'])}; candidate preserved C1 C = {n(candidate['correspondence'])}.\n")
rows=[]
for label,z,domain in [('B immediately before Commit',b['resolved'],'settlement'),('Recorded C1',c['resolved'],a['recorded_snapshot']['direction_after']),('Candidate C1',candidate,candidate['direction_after'])]:rows.append([label,*[n(z[k]) for k in ['correspondence','chirality','closure','tension','charge']],domain])
(p/'COMPARISON.md').write_text('# Exact Commit comparison\n\n'+table(['Observation','Correspondence','Chirality','Closure','Tension','Charge','Domain'],rows)+'\n## Runtime identities\n\n'+table(['Label','Proposition ID'],[[v,k] for k,v in names.items()]))
if child:
 rows=[]
 for label,z in [('Child raw local',child['raw_local']),('Child ordinary comparator',child['production_comparator']['resolved']),('Child with canonical candidate baseline',child['candidate'])]:rows.append([label,*[n(z[k]) for k in ['correspondence','chirality','closure','tension','charge']]])
 (p/'CHILD.md').write_text('# One lawful expressive descendant\n\nThe real offered Variant from C1 is dispatched through ParticipationService. Both Commit observations resolve to Settlement, so the same immediate child morphology is used for this read-only measurement comparison. Candidate scalar substitution is confined to C1’s ancestor contribution, not the child’s local state or production database.\n\n'+table(['Observation','Correspondence','Chirality','Closure','Tension','Charge'],rows)+'\n## Candidate lineage traversal\n\n'+table(['Member','Distance','Weight'],[[name(z['id']),z['distance'],z['weight']] for z in child['candidate_positions']])+f"\nLocal intervals: {child['local']['current_history']['intervals']}. Local Orientation: {n(child['local']['orientation'])}; local Cadence: {n(child['local']['attestation_cadence'])}; local Chirality: {n(child['local']['local_chirality'])}. Own effective Attestations: {child['local']['own_effective_attestations']}. Incoming cursor event order: {child['local']['previous_boundary_beat']}; provenance: {child['local']['cursor_provenance']}.\n\nAttestation/Receipt counts before: {child['before_counts']}; after: {child['after_counts']}. Existing local snapshots unchanged: {all(child['existing_local_snapshots_unchanged'].values())}. Production Commit snapshot unchanged: {child['commit_snapshot_unchanged']}.\n\nExact input: `{json.dumps(child['input'])}`. The full offered schema and before/after states are in hard-edge.json. No further action is taken.\n")
print(json.dumps({'candidate':candidate,'child':child['candidate'] if child else None},indent=2))
