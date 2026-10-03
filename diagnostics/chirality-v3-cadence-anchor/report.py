"""Render runtime traces and archived comparators; never synthesize downstream anchor fields."""
import json
from pathlib import Path
P=Path(__file__).parent
load=lambda p:json.loads(p.read_text())
a=load(P/'candidate-trace.json'); b=load(P.parent/'chirality-v3-local-attestation/candidate-trace.json'); v=load(P.parent/'chirality-v3-positive-window/window-trace.json')
auth=load(P.parent/'chirality-v3-positive-window/result-3-sparse_5.json')
rows={r['label']:r for r in a['boundaries']}; br={r['label']:r for r in b['boundaries']}; vr={r['label']:r for r in v['boundaries']}
names={vr['origin']['states'][0]['gate_id']:'Origin',vr['continuation:0']['states'][1]['gate_id']:'P1',vr['continuation:2']['states'][0]['gate_id']:'D1'}
for n,name in {1:'Q1',6:'S1',10:'D2',11:'P2',15:'Q2',17:'S2',18:'R',35:'D3',36:'P3',40:'Q3',42:'S3',43:'R2',51:'R3',77:'D4',78:'P4'}.items():
 names[vr[f'continuation:{n}']['states'][auth['continuation']['actions'][n]['actor']]['gate_id']]=name
rev={**{vv:k for k,vv in b['identity_map'].items()},**{vv:k for k,vv in a['identity_map'].items()}}
def name(i):return names.get(rev.get(i,i),i)
def num(x):return 'undefined / not recorded' if x is None else f'{x:.7f}'
def seq(x):return '['+','.join(map(str,x))+']'
def table(h,rs):return '\n'.join(['| '+' | '.join(h)+' |','| '+' | '.join(['---']*len(h))+' |']+['| '+' | '.join(str(z).replace('|','/').replace('\n',' ') for z in r)+' |' for r in rs])+'\n'
def directions(f):return f['directions']['orientation_direction']+'/'+f['directions']['attestation_cadence_direction']
# Every successful primary state, including no-op local observations during structural progress.
trace=[];fields=[];checks=[]
for r in a['boundaries']:
 for s in r['states']:
  f=s['field'];d=f['local'];h=d['current_history']
  trace.append([r['label'],s['actor'],s['cycle'],s['phase'],name(s['gate_id']),d['cadence_anchor'],d['anchor_activated'],seq(d['local_completed_intervals']),seq(h['intervals']),d['cadence_status'],num(h['mean']),num(h['stddev']),num(h['cv']),num(d['attestation_cadence']),num(d['orientation']),directions(f),num(d['local_chirality'])])
  fields.append([r['label'],s['actor'],name(s['gate_id']),num(f['normalized_ancestral_chirality']),num(f['chirality']),num(f['correspondence']),num(f['closure']),num(f['tension']),num(f['charge']),s['grounding']['admissible'],s['bias_structurally_reached'],s['bias_generation'],s['four_way_overlap'],','.join(s['available_actions'])])
 for ident,check in (r['invariants'] or {}).items():checks.append({'label':r['label'],'proposition':name(ident),'cycle_entry_added':bool(r['created']['cycles']),**check})
(P/'TRACE.md').write_text('# Runtime primary trace through the last defined boundary\n\nDormant anchors do not enter samples before an own Attestation. Existing local snapshots remain cached between own-Attestation changes. Full effective vote IDs, measurements, attenuation terms and schemas are in the lossless JSON.\n\n'+table(['Boundary','Actor','Cycle','Phase','Source','Anchor','Activated','Own completed intervals','Cadence samples','Maturity','Mean','SD','CV','AC','O','O/AC directions','Local Chirality'],trace)+'\n## Field and lifecycle\n\n'+table(['Boundary','Actor','Source','Normalized ancestors','Field Chirality','Correspondence','Closure','Tension','Charge','Grounded','Bias reached','Bias generation','Overlap','Actions'],fields))
# Focused three-model comparisons: missing candidate boundaries stay missing.
focus=[('D1 mature','prefix:32','D1',None),('D1 -> P1 birth','prefix:3','P1',None),('P1 first vote','continuation:0','P1',1),('P1 -> Q1','continuation:1','Q1',None),('Q1 -> S1','continuation:6','S1',None),('S1 first vote','continuation:8','S1',2),('P2 -> Q2','continuation:15','Q2',None),('Mediate S2','continuation:17','S2',None),('Reconcile / first Bias','continuation:18','R',0),('R grounded Bias','continuation:33','R',1)]
comp=[];cfields=[]
for title,label,alias,actor in focus:
 for mode,source in [('A: live v3',vr),('B: complete reset',br),('C: Cadence Anchor',rows)]:
  if label not in source:
   comp.append([title,label,mode]+['UNDEFINED FROM c8']*8);cfields.append([title,mode]+['UNDEFINED FROM c8']*9);continue
  s=next(s for s in source[label]['states'] if name(s['gate_id'])==alias and (actor is None or s['actor']==actor))
  f=s['field'];d=f if mode.startswith('A') else f['local'];h=d['current_history'];lc=f['local_chirality'] if mode.startswith('A') else d['local_chirality']
  comp.append([title,label,mode,seq(h['intervals']),d['cadence_status'],num(d['attestation_cadence']),num(d['orientation']),directions(f),num(lc),num(f['chirality']),num(f['tension'])])
  if mode.startswith('A'):
   ground=s['predicates']['grounding']['admissible'];reach=s['phase']==6 and s['predicates']['accepts_gate'];generation=s['predicates']['reclassify_generation'] if s['phase']==6 else None
   matched=next(t for t in br[label]['states'] if name(t['gate_id'])==alias and t['actor']==s['actor'])
   inherited='~'+num(f['chirality']-lc/matched['field']['attenuation_mass'])
  else:ground=s['grounding']['admissible'];reach=s['bias_structurally_reached'];generation=s['bias_generation'];inherited=num(f['normalized_ancestral_chirality'])
  cfields.append([title,mode,inherited,num(f['correspondence']),num(f['closure']),num(f['charge']),ground,reach,generation,f['directions']['reason'],','.join(s['available_actions'])])
(P/'COMPARISON.md').write_text('# Same history, three models\n\nA and B are the previously completed real-service baselines. C is this run. Values beyond c8 are intentionally not assigned to C. `None` for Bias generation means the actor is not at Bias. In the second table only the v3 ancestor contribution is inferred (~) by subtracting its weighted head from the recorded total using the unchanged production attenuation mass; these inferred terms are approximate because the source values were already rounded.\n\n'+table(['Boundary','Label','Model','Samples','Maturity','Cadence','Orientation','Directions','Local Chirality','Field Chirality','Tension'],comp)+'\n'+table(['Boundary','Model','Normalized ancestors','Correspondence','Closure','Charge','Grounded','Bias reachable','Bias generation','Direction rejection','Actions'],cfields))
# Actual anchor transfers, with first successor if reached.
birthrows=[]
for ident,z in a['anchor_births'].items():
 obs=[r['local_snapshots'][ident] for r in a['boundaries'] if ident in r['local_snapshots'] and r['local_snapshots'][ident]['attestation_observed']]
 first=obs[0] if obs else (a['undefined']['attempted_observation'] if a['undefined']['proposition_id']==ident else None)
 birthrows.append([name(z['parent_id']),seq(z['parent_local_completed_intervals']),z['parent_own_act_count'],name(ident),z['anchor'],z['endpoint_order'],seq(first['current_history']['act_order']) if first else 'none',first['first_successor_interval'] if first else 'none',seq(first['current_history']['intervals']) if first else 'dormant',first['cadence_status'] if first else 'fresh'])
# Entire original history birth audit, explicitly not a candidate continuation.
audit=[]
for r in b['boundaries']:
 for e in r['events']:
  if e['event']!='proposition_write':continue
  f=e['field']
  for parent in f['parents']:
   h=r['local_snapshots'][parent]['current_history']
   audit.append([r['label'],name(parent),name(f['id']),seq(h['intervals']),seq(h['act_order']),'runtime candidate reached' if r['label'] in rows else 'baseline audit only; candidate stopped'])
(P/'ANCHORS.md').write_text('# Actual birth-time anchor propagation\n\nThe final row includes an attempted real Attestation that rolled back when CV was undefined. No downstream field value was produced.\n\n'+table(['Parent','Completed intervals at birth','Parent own acts','Child','Anchor','Endpoint order','Child own act orders at first vote','First successor','Samples','Status'],birthrows)+'\n## Full preserved history: parent observations at each birth\n\nThese are complete-reset archive observations, not an operational anchor continuation. They establish that most births precede any completed own interval. Multi-parent Reconcile requires an explicit predecessor-selection rule. No missing anchor is retroactively filled from later parent observations.\n\n'+table(['Birth','Parent','Child','Parent own intervals then','Parent own act orders then','Evidence scope'],audit))
(P/'IDENTITIES.md').write_text('# Proposition identity mapping\n\n'+table(['Alias','Original archive ID','Complete reset replay ID','Anchor replay ID'],[[alias,i,b['identity_map'].get(i,'—'),a['identity_map'].get(i,'not reached')] for i,alias in names.items()]))
(P/'invariants.json').write_text(json.dumps(checks,indent=2)+'\n')
single=[]
for mode in ['reset','anchor']:
 z=load(P/f'single-{mode}.json')
 for i,d in z['local_boundaries'][-1]['locals'].items():single.append([mode,i,d['current_history']['acts'],seq(d['current_history']['intervals']),d.get('cadence_anchor'),d['cadence_status'],num(d['orientation']),num(d['attestation_cadence']),num(d['local_chirality'])])
(P/'SINGLE-PARTICIPANT.md').write_text('# Existing single-participant 17-action prefix\n\nBoth models perform the same 17 archived lawful actions with one participant. Each ordinary proposition has one own Attestation at the end and had no completed interval to pass at its child birth. Therefore this prefix cannot seed an anchor: both models remain locally Cadence-immature. This is not evidence for universal perpetual blocking, nor evidence that a pre-seeded nonzero anchor cannot help another history. The Origin constitutional value is preserved.\n\n'+table(['Model','Proposition','Own acts','Samples','Anchor','Maturity','Orientation','Cadence','Local Chirality'],single))
summary={'stop':a['stop'],'completed_actions':a['completed_actions'],'boundaries':len(a['boundaries']),'participants':a['participants'],'ordinary_commits':a['ordinary_commits'],'overlaps':a['four_way_overlaps'],'local_invariance_checks':len(checks),'unchanged':sum(x['entire_local_snapshot_unchanged'] for x in checks),'own_changed':sum(x['own_attestation_changed'] for x in checks),'cycle_invariant_checks':sum(x['cycle_entry_added'] and x['entire_local_snapshot_unchanged'] for x in checks),'undefined':a['undefined']}
(P/'summary.json').write_text(json.dumps(summary,indent=2)+'\n');print(json.dumps({k:v for k,v in summary.items() if k!='undefined'},indent=2))
