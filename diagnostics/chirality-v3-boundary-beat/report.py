"""Render runtime traces and archived comparators; never synthesize downstream anchor fields."""
import json
from pathlib import Path
P=Path(__file__).parent
load=lambda p:json.loads(p.read_text())
c=load(P.parent/'chirality-v3-cadence-anchor/candidate-trace.json'); cr={r['label']:r for r in c['boundaries']};
a=load(P/'candidate-trace.json'); b=load(P.parent/'chirality-v3-local-attestation/candidate-trace.json'); v=load(P.parent/'chirality-v3-positive-window/window-trace.json')
auth=load(P.parent/'chirality-v3-positive-window/result-3-sparse_5.json')
rows={r['label']:r for r in a['boundaries']}; br={r['label']:r for r in b['boundaries']}; vr={r['label']:r for r in v['boundaries']}
names={vr['origin']['states'][0]['gate_id']:'Origin',vr['continuation:0']['states'][1]['gate_id']:'P1',vr['continuation:2']['states'][0]['gate_id']:'D1'}
for n,name in {1:'Q1',6:'S1',10:'D2',11:'P2',15:'Q2',17:'S2',18:'R',35:'D3',36:'P3',40:'Q3',42:'S3',43:'R2',51:'R3',77:'D4',78:'P4'}.items():
 names[vr[f'continuation:{n}']['states'][auth['continuation']['actions'][n]['actor']]['gate_id']]=name
rev={**{vv:k for k,vv in c['identity_map'].items()},**{vv:k for k,vv in b['identity_map'].items()},**{vv:k for k,vv in a['identity_map'].items()}}
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
  trace.append([r['label'],s['actor'],s['cycle'],s['phase'],name(s['gate_id']),d.get('previous_boundary_beat'),d['boundary_beat_used'],seq(d['local_completed_intervals']),seq(h['intervals']),d['cadence_status'],num(h['mean']),num(h['stddev']),num(h['cv']),num(d['attestation_cadence']),num(d['orientation']),directions(f),num(d['local_chirality'])])
  fields.append([r['label'],s['actor'],name(s['gate_id']),num(f['normalized_ancestral_chirality']),num(f['chirality']),num(f['correspondence']),num(f['closure']),num(f['tension']),num(f['charge']),s['grounding']['admissible'],s['bias_structurally_reached'],s['bias_generation'],s['four_way_overlap'],','.join(s['available_actions'])])
 for ident,check in (r['invariants'] or {}).items():checks.append({'label':r['label'],'proposition':name(ident),'cycle_entry_added':bool(r['created']['cycles']),**check})
(P/'TRACE.md').write_text('# Runtime primary trace through the last defined boundary\n\nStored boundary beats do not create an interval before an own Attestation. Existing local snapshots remain cached between own-Attestation changes. Full effective vote IDs, measurements, attenuation terms and schemas are in the lossless JSON.\n\n'+table(['Boundary','Actor','Cycle','Phase','Source','Boundary event order','Used','Own completed intervals','Cadence samples','Maturity','Mean','SD','CV','AC','O','O/AC directions','Local Chirality'],trace)+'\n## Field and lifecycle\n\n'+table(['Boundary','Actor','Source','Normalized ancestors','Field Chirality','Correspondence','Closure','Tension','Charge','Grounded','Bias reached','Bias generation','Overlap','Actions'],fields))
# Focused three-model comparisons: missing candidate boundaries stay missing.
focus=[('D1 original mature point','prefix:32','D1',None),('D1 first candidate maturity','prefix:15','D1',None),('P1 first mature local cadence','continuation:4','P1',0),('D1 -> P1 birth','prefix:3','P1',None),('P1 first vote','continuation:0','P1',1),('P1 -> Q1','continuation:1','Q1',None),('Q1 -> S1','continuation:6','S1',None),('S1 first vote','continuation:8','S1',2),('P2 -> Q2','continuation:15','Q2',None),('Mediate S2','continuation:17','S2',None),('Reconcile / first Bias','continuation:18','R',0),('R grounded Bias','continuation:33','R',1)]
comp=[];cfields=[]
for title,label,alias,actor in focus:
 for mode,source in [('A: live v3',vr),('B: complete reset',br),('C: completed-interval anchor',cr),('D: boundary beat',rows)]:
  if label not in source:
   comp.append([title,label,mode]+['not reached / unresolved']*8);cfields.append([title,mode]+['not reached / unresolved']*9);continue
  s=next(s for s in source[label]['states'] if name(s['gate_id'])==alias and (actor is None or s['actor']==actor))
  f=s['field'];d=f if mode.startswith('A') else f['local'];h=d['current_history'];lc=f['local_chirality'] if mode.startswith('A') else d['local_chirality']
  comp.append([title,label,mode,seq(h['intervals']),d['cadence_status'],num(d['attestation_cadence']),num(d['orientation']),directions(f),num(lc),num(f['chirality']),num(f['tension'])])
  if mode.startswith('A'):
   ground=s['predicates']['grounding']['admissible'];reach=s['phase']==6 and s['predicates']['accepts_gate'];generation=s['predicates']['reclassify_generation'] if s['phase']==6 else None
   matched=next(t for t in br[label]['states'] if name(t['gate_id'])==alias and t['actor']==s['actor'])
   inherited='~'+num(f['chirality']-lc/matched['field']['attenuation_mass'])
  else:ground=s['grounding']['admissible'];reach=s['bias_structurally_reached'];generation=s['bias_generation'];inherited=num(f['normalized_ancestral_chirality'])
  cfields.append([title,mode,inherited,num(f['correspondence']),num(f['closure']),num(f['charge']),ground,reach,generation,f['directions']['reason'],','.join(s['available_actions'])])
(P/'COMPARISON.md').write_text('# Same history, four diagnostic models\n\nA and B are previously completed real-service baselines. C stopped at c8; D is this boundary-beat run and stops at c5. Missing states are not extrapolated. A is the lineage-live developmental v3 diagnostic comparator, not a claim that its provisional formula is canonical production behavior. `None` for Bias generation means the actor is not at Bias. In the second table only the v3 ancestor contribution is inferred (~) by subtracting its weighted head from the recorded total using the unchanged production attenuation mass; these inferred terms are approximate because the source values were already rounded.\n\n'+table(['Boundary','Label','Model','Samples','Maturity','Cadence','Orientation','Directions','Local Chirality','Field Chirality','Tension'],comp)+'\n'+table(['Boundary','Model','Normalized ancestors','Correspondence','Closure','Charge','Grounded','Bias reachable','Bias generation','Direction rejection','Actions'],cfields))
# Boundary-beat propagation and complete archive audit.
birthrows=[]
for ident,z in a['beat_births'].items():
 obs=[r['local_snapshots'][ident] for r in a['boundaries'] if ident in r['local_snapshots'] and r['local_snapshots'][ident]['attestation_observed']]
 first=obs[0] if obs else None
 birthrows.append([name(z['parent_id']),z['parent_own_act_count'],seq(z['parent_intervals_audit_only']),name(ident),z['endpoint_order'],seq(first['current_history']['act_order']) if first else 'none',first['first_successor_interval'] if first else 'none',seq(first['current_history']['intervals']) if first else 'empty'])
audit=[]
for r in b['boundaries']:
 for e in r['events']:
  if e['event']!='proposition_write':continue
  for parent in e['field']['parents']:
   h=r['local_snapshots'][parent]['current_history']
   audit.append([r['label'],name(parent),name(e['field']['id']),seq(h['act_order']),'MISSING OWN BEAT' if not h['acts'] else 'own beat exists','runtime reached' if r['label'] in rows else 'baseline audit only'])
(P/'BEATS.md').write_text('# Boundary-beat propagation\n\nOnly the endpoint is carried; parent interval lists below are audit provenance, never samples. Endpoint is frozen at child birth.\n\n'+table(['Parent','Own acts at birth','Parent intervals (audit only)','Child','Endpoint event order','First child own act order','First interval belonging to child','First local samples'],birthrows)+'\n## Every birth in the preserved archive\n\nLater entries are read-only archival findings, not candidate lifecycle results. The first immediate predecessor with no own beat in the primary archive is D2→P2 at c11, after this run stops at c5.\n\n'+table(['Birth','Parent','Child','Parent own act orders','Beat availability','Scope'],audit))
(P/'IDENTITIES.md').write_text('# Proposition identity mapping\n\n'+table(['Alias','Original archive ID','Complete reset replay ID','Anchor replay ID'],[[alias,i,b['identity_map'].get(i,'—'),a['identity_map'].get(i,'not reached')] for i,alias in names.items()]))
(P/'invariants.json').write_text(json.dumps(checks,indent=2)+'\n')
single=[]
for mode in ['reset','beat']:
 z=load(P/f'single-{mode}.json')
 for i,d in z['local_boundaries'][-1]['locals'].items():single.append([mode,i,d['current_history']['acts'],seq(d['current_history']['intervals']),d.get('previous_boundary_beat'),d['cadence_status'],num(d['orientation']),num(d['attestation_cadence']),num(d['local_chirality'])])
(P/'SINGLE-PARTICIPANT.md').write_text('# Existing single-participant control\n\nReset completes the archived first17 actions. Boundary beat completes the first action, then its next Variant D1→P1 encounters immediate predecessor D1 with no Attestation. The real service rolls back that attempted child write. This is the explicit ambiguity guard; no ancestral beat or creation position is substituted. Consequently this unchanged control cannot establish mature cross-proposition continuity.\n\n'+table(['Model','Proposition','Own acts','Samples','Boundary beat','Maturity','Orientation','Cadence','Local Chirality'],single))
summary={'stop':a['stop'],'completed_actions':a['completed_actions'],'boundaries':len(a['boundaries']),'participants':a['participants'],'ordinary_commits':a['ordinary_commits'],'overlaps':a['four_way_overlaps'],'local_invariance_checks':len(checks),'unchanged':sum(x['entire_local_snapshot_unchanged'] for x in checks),'own_changed':sum(x['own_attestation_changed'] for x in checks),'cycle_invariant_checks':sum(x['cycle_entry_added'] and x['entire_local_snapshot_unchanged'] for x in checks),'undefined':a['undefined']}
(P/'summary.json').write_text(json.dumps(summary,indent=2)+'\n');print(json.dumps({k:v for k,v in summary.items() if k!='undefined'},indent=2))

# Event trace with actual represented Cycle positions, distinct from event-order coordinates.
events=[];efields=[]
for r in a['boundaries']:
 authored_event=r.get('input') or {}
 actor=0 if r['label']=='origin' else (auth['actions'] if r['label'].startswith('prefix:') else auth['continuation']['actions'])[int(r['label'].split(':')[1])]['actor']
 current_state=r['states'][actor]
 items=r['events'] or [{'event':'cycle_only_progression' if r['created']['cycles'] else 'structural_action','field':current_state['field']}]
 for e in items:
  f=e['field'];d=f['local'];h=d['current_history'];acts=h['act_order'];cycles=h['represented_cycle_order']
  att=e['event'].startswith('attestation')
  current=acts[-1] if att and acts else None
  previous=(acts[-2] if len(acts)>1 else d.get('previous_boundary_beat')) if att else None
  pos=lambda order:sum(n<order for n in cycles) if order is not None else None
  new=h['intervals'][-1] if att and h['intervals'] else None
  stable=sum(z['entire_local_snapshot_unchanged'] for z in (r['invariants'] or {}).values())
  events.append([r['label'],e['event'],authored_event.get('action','Origin'),f"A{actor}/C{current_state['cycle']}/P{current_state['phase']}",name(f['id']),','.join(name(i) for i in f['parents']),f['domain'],f['producing_phase'],previous,current,pos(previous),pos(current),new,seq(h['intervals']),d['cadence_status'],num(h['mean']),num(h['stddev']),num(h['cv']),num(d['attestation_cadence']),num(d['orientation']),directions(f),num(d['local_chirality']),stable])
  efields.append([r['label'],name(f['id']),num(f['normalized_ancestral_chirality']),num(f['chirality']),num(f['correspondence']),num(f['closure']),num(f['tension']),num(f['charge'])])
(P/'EVENTS.md').write_text('# Event-oriented primary trace\n\nProducing Phase identifies the proposition; current participant Phases/Cycles and available actions are in TRACE.md. For Attestation events, Cycle position is the number of represented entries before that beat in this proposition’s scope. Event-order endpoints preserve exact temporal ordering; they are not Cycle counts. Non-Attestation rows show unchanged stored observations, not fresh measurements. Stable count covers all existing propositions whose complete local snapshot remains identical. Origin keeps its constitutional calibration.\n\n'+table(['Boundary','Event','Action','Acting participant/Cycle/Phase after action','Proposition or current Gate','Parents','Domain','Producing Phase','Previous event order','Current event order','Previous Cycle position','Current Cycle position','New interval','Local intervals','Maturity','Mean','SD','CV','Cadence','Orientation','Directions','Local Chirality','Unchanged snapshots'],events)+'\n## Field at the same event\n\n'+table(['Boundary','Proposition','Normalized ancestry','Field Chirality','Correspondence','Closure','Tension','Charge'],efields))

u=a['undefined']['attempted_observation']; h=u['current_history']; previous=h['act_order'][-2];current=h['act_order'][-1];position=lambda n:sum(c<n for c in h['represented_cycle_order'])
with (P/'EVENTS.md').open('a') as f:
 f.write('\n## Terminal attempted event (rolled back)\n\n'+table(['Boundary','Target','Previous event order','Current event order','Previous Cycle position','Current Cycle position','New interval','Local intervals','Mean','SD','CV','Orientation','Cadence / local Chirality / field'],[[a['blocked']['label'],name(u['proposition_id']),previous,current,position(previous),position(current),h['intervals'][-1],seq(h['intervals']),h['mean'],h['stddev'],'undefined',u['orientation'],'not assigned']]))
