"""Render production snapshots; never generate or modify measurements."""
import json
from pathlib import Path
P=Path(__file__).parent
a=json.loads((P/'candidate-trace.json').read_text()); rows={r['label']:r for r in a['boundaries']}
names={}
for label,name in [('origin','Origin'),('prefix:0','D1'),('prefix:3','P1'),('continuation:1','Q1'),('continuation:6','S1'),('continuation:10','D2'),('continuation:11','P2'),('continuation:15','Q2'),('continuation:17','S2'),('continuation:18','R'),('post-bias:0','B')]:
 if label in rows:
  for e in rows[label]['events']:
   if e['event']=='proposition_write':names[e['id']]=name
for r in a['boundaries']:
 for e in r['events']:
  if e['event']=='proposition_write' and e['field'].get('commitment_snapshot') and e['id'] not in names:names[e['id']]='C1'
def name(i):return names.get(i,i)
def num(x):return '—' if x is None else f'{x:.7f}'
def table(h,rs):return '\n'.join(['| '+' | '.join(h)+' |','| '+' | '.join(['---']*len(h))+' |']+['| '+' | '.join(str(z).replace('|','/').replace('\n',' ') for z in r)+' |' for r in rs])+'\n'
def dirs(f):return f['directions']['orientation_direction']+'/'+f['directions']['attestation_cadence_direction']
focus=[r for r in a['boundaries'] if r['label']=='continuation:21' or r['label'].startswith('post-bias:')]
frows=[];lrows=[];events=[];arows=[];br=[];inv=[];ids=[];commits=[]
for r in focus:
 for s in r['states']:
  if 'field' not in s:
   lrows.append([r['label'],s['actor'],'—','—','—',s['state']]);continue
  f=s['field'];d=f['local'];h=d['current_history']
  frows.append([r['label'],s['actor'],s['cycle'],s['phase'],s['domain'],name(f['id']),len(d['own_effective_attestations']),h['intervals'],d['cadence_status'],num(d['orientation']),num(d['attestation_cadence']),dirs(f),num(d['local_chirality']),num(f['normalized_ancestral_chirality']),num(f['chirality']),num(f['correspondence']),num(f['closure']),num(f['tension']),num(f['charge'])])
  lrows.append([r['label'],s['actor'],s['cycle'],s['phase'],name(f['id']),json.dumps({'actions':s['available_actions'],'grounding':s['grounding'],'Bias structurally reached':s['bias_structurally_reached'],'Bias generation':s['bias_generation'],'Commit':s.get('commit_admissibility')})])
 for e in r['events']:
  f=e['field'];d=f['local'];h=d['current_history'];orders=h['act_order'];att=e['event'].startswith('attestation')
  prev=(orders[-2] if len(orders)>1 else d['previous_boundary_beat']) if att else d['previous_boundary_beat'];current=orders[-1] if att else None
  pos=lambda n:sum(c<n for c in h['represented_cycle_order']) if n is not None else None
  events.append([r['label'],r['input'],e['event'],name(f['id']),','.join(name(x) for x in f['parents']),prev,current,pos(prev),pos(current),h['intervals'][-1] if att and h['intervals'] else None,h['intervals'],h['mean'],h['stddev'],h.get('raw_cv'),h['cv'],h.get('zero_mean_regular'),d['cadence_status'],num(d['orientation']),num(d['attestation_cadence']),dirs(f),num(d['local_chirality']),num(f['normalized_ancestral_chirality']),num(f['chirality']),num(f['correspondence']),num(f['closure']),num(f['tension']),num(f['charge'])])
  for p in f['attenuation_parts']:arows.append([r['label'],name(f['id']),name(p['id']),p['distance'],p['weight'],num(p['local_chirality']),num(p['weighted_chirality']/f['attenuation_mass'])])
  if f.get('commitment_snapshot'):commits.append({'label':r['label'],'field':f})
 for ident,check in r['invariants'].items():inv.append([r['label'],name(ident),check['own_attestation_changed'],check['entire_local_snapshot_unchanged']])
for id,b in a['beat_births'].items():
 if name(id) in ('B','C1'):br.append([name(id),name(b['parent_id']),b['edge_type'],b['endpoint_order'],b['provenance'],[name(i) for i in b['cursor_path']]])
for id,n in names.items():ids.append([n,id])
(P/'TRACE.md').write_text('# Post-c21 production trace\n\nAll values are captured through the real services with the unchanged isolated candidate adapter. Effective votes below are own rows; Commit source-by-reference grounding is separately recorded. No field threshold is imposed. `B` is the Bias result. Complete replay and action schemas are in candidate-trace.json.\n\n'+table(['Boundary','Actor','Cycle','Phase','Domain','Gate','Own effective votes','Intervals','Maturity','O','AC','O/AC directions','Local χ','Normalized ancestral χ','Field χ','C','Closure','Tension','Charge'],frows)+'\n## Actual available actions and predicates\n\n'+table(['Boundary','Actor','Cycle','Phase','Gate','Production state'],lrows)+'\n## Exact proposition identities\n\n'+table(['Label','ID'],ids))
(P/'EVENTS.md').write_text('# New events only\n\nEvent order distinguishes acts from entries; Cycle position counts represented entries before the beat. Structural writes do not append samples. Raw CV can be undefined while effective CV is explicitly zero for a mature all-zero sequence.\n\n'+table(['Boundary','Dispatched input','Event','Target','Parents','Previous beat order','Act order','Previous Cycle position','Current Cycle position','New interval','Local intervals','Mean','SD','Raw CV','Effective CV','Zero special','Maturity','O','AC','Directions','Local χ','Normalized ancestry','Field χ','C','Closure','Tension','Charge'],events)+'\n## Cursor propagation\n\n'+table(['Child','Source','Edge','Incoming beat order','Provenance','Cursor path'],br)+'\n## Complete local-snapshot invariance\n\n'+table(['Boundary','Proposition','Own Attestation changed','Entire snapshot unchanged'],inv))
(P/'ATTENUATION.md').write_text('# Production inverse-square attenuation\n\nEach row is a real ancestry member at its production minimum distance, normalized by total lineage weight. Shared ancestry is counted once. The head contribution is separate from ancestral influence.\n\n'+table(['Boundary','Head','Member','Distance','Weight','Local χ','Normalized contribution'],arows))
choices=next((d['receipt_choices'] for d in a.get('decisions',[]) if 'receipt_choices' in d),[])
receiptrows=[]
for r in choices:
 provenance=next((b['label'] for b in a['boundaries'] if any(x['id']==r['id'] for x in b['created']['receipts'])),None)
 receiptrows.append([r['id'],provenance,name(r['receiptable_id']),r['reference'],r['phase_id'],r['instrument_execution_id'],r.get('context_horizon_id')])
(P/'RECEIPTS.md').write_text('# c21 authorized Receipt choices\n\nSelection: first Receipt in actual schema ordering, following NaturalRhythmTest. The reference selected was announced before runtime dispatch. No field-based comparison or optimization.\n\n'+table(['Receipt ID','Authored boundary','Original target','Reference','Producing Phase','Execution','Horizon'],receiptrows))
checks=[v for r in a['boundaries'] for v in (r['invariants'] or {}).values()]
summary={'stop':a['stop'],'completed_actions':a['completed_actions'],'new_actions':len([r for r in focus if r['label'].startswith('post-bias:')]),'ordinary_commits':a['ordinary_commits'],'participants':a['participants'],'undefined':a['undefined'],'local_invariance_checks':len(checks),'unchanged':sum(c['entire_local_snapshot_unchanged'] for c in checks),'own_changes':sum(c['own_attestation_changed'] for c in checks)}
(P/'summary.json').write_text(json.dumps(summary,indent=2)+'\n')
(P/'commit-boundary.json').write_text(json.dumps(commits,indent=2)+'\n')
print(json.dumps(summary,indent=2))
