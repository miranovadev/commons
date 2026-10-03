"""Render real runtime measurements and preserved v3 comparisons; no measurement formulas."""
import json
from pathlib import Path
P=Path(__file__).parent
d=json.loads((P/'candidate-trace.json').read_text())
old=json.loads((P.parent/'chirality-v3-positive-window/window-trace.json').read_text())
authored=json.loads((P.parent/'chirality-v3-positive-window/result-3-sparse_5.json').read_text())
base={r['label']:r for r in old['boundaries']}; rows={r['label']:r for r in d['boundaries']}; rev={v:k for k,v in d['identity_map'].items()}
names={base['origin']['states'][0]['gate_id']:'Origin',base['continuation:0']['states'][1]['gate_id']:'P1',base['continuation:2']['states'][0]['gate_id']:'D1'}
for n,name in {1:'Q1',6:'S1',10:'D2',11:'P2',15:'Q2',17:'S2',18:'R',35:'D3',36:'P3',40:'Q3',42:'S3',43:'R2',51:'R3',77:'D4',78:'P4'}.items():
    a=authored['continuation']['actions'][n]['actor'];names[base[f'continuation:{n}']['states'][a]['gate_id']]=name
def name(i):return names.get(rev.get(i,i),i)
def num(v):return '—' if v is None else f'{v:.7f}'
def seq(x):return '['+','.join(map(str,x))+']'
def table(h,r):return '\n'.join(['| '+' | '.join(h)+' |','| '+' | '.join(['---']*len(h))+' |']+['| '+' | '.join(str(v).replace('|','/').replace('\n',' ') for v in row)+' |' for row in r])+'\n'
def direction(f):return f['directions']['orientation_direction']+'/'+f['directions']['attestation_cadence_direction']
def fieldsummary(f, candidate):
    l=f['local'] if candidate else f
    return [num(l['orientation']),seq(l['current_history']['intervals']),num(l['attestation_cadence']),direction(f),num(f['chirality']),num(f['tension'])]
focus=[('A: mature D1','prefix:32','D1'),('B: P1 grounding','continuation:0','P1'),('C: Reclassify Q1','continuation:1','Q1'),('D: first Mediate S1','continuation:6','S1'),('D: ready to Reconcile S2','continuation:17','S2'),('E: first Bias R','continuation:18','R'),('F: grounded Bias R','continuation:33','R')]
comparison=[];focus_data=[]
for title,label,alias in focus:
    if label not in rows:continue
    ident=next(k for k,v in names.items() if v==alias);s=next((s for s in rows[label]['states'] if rev.get(s.get('gate_id'))==ident and (title[0]!='F' or s['actor']==1)),None)
    b=next((s for s in base[label]['states'] if s.get('gate_id')==ident and (title[0]!='F' or s['actor']==1)),None)
    if s is None or b is None:continue
    for mode,z,flag in [('v3',b,False),('candidate',s,True)]:
        ground=z['grounding']['admissible'] if flag else z['predicates']['grounding']['admissible']
        generation=z['bias_generation'] if flag else (z['predicates']['reclassify_generation'] if z['phase']==6 else None)
        comparison.append([title,label,mode,f'A{z["actor"]}/C{z["cycle"]}/P{z["phase"]}',*fieldsummary(z['field'],flag),ground,generation,','.join(z['available_actions'])])
    focus_data.append({'title':title,'label':label,'candidate':s,'v3':b})
(P/'COMPARISON.md').write_text('# Same authored boundaries: current v3 versus candidate\n\nComponent columns are raw/local. Chirality is the production attenuated aggregate. `None` in Bias generation means the actor is not at Bias. Positive Tension is not a Bias admission predicate. F is A1 immediately before the preserved c34 turn, at which Rest is selected because Bias Reclassify is unavailable; no forbidden Bias write was attempted.\n\n'+table(['Boundary','Label','Model','Actor/Cycle/Phase','Orientation','Intervals','Cadence','O/AC directions','Chirality','Tension','Grounded','Bias generation','Available actions'],comparison))
(P/'IDENTITIES.md').write_text('# Same authored propositions, fresh replay identifiers\n\n'+table(['Alias','Positive-window archive ID','Candidate replay ID'],[[v,k,d['identity_map'].get(k,'not reached')] for k,v in names.items()]))
(P/'focus-boundaries.json').write_text(json.dumps(focus_data,indent=2)+'\n')

events=[];projection=[]
for r in d['boundaries']:
    for e in r['events']:
        f=e['field'];l=f['local'];h=l['current_history'];alias=name(f['id'])
        states=[s for s in r['states'] if s.get('gate_id')==f['id']]
        loc='; '.join(f'A{s["actor"]}/C{s["cycle"]}/P{s["phase"]}' for s in states)
        acts=','.join(s['available_actions'][0] if len(s['available_actions'])==1 else '/'.join(s['available_actions']) for s in states)
        events.append([r['label'],e['event'],alias,f['id'],','.join(name(x) for x in f['parents']),f['domain'],f'C{f["creating_cycle"]}/P{f["producing_phase"]}',len(l['own_effective_attestations']),num(l['orientation']),f['directions']['orientation_direction'],seq(h['intervals']),h['usable_interval_count'],l['cadence_status'],num(h['mean']),num(h['stddev']),num(h['cv']),num(l['attestation_cadence']),f['directions']['attestation_cadence_direction'],num(l['local_chirality'])])
        projection.append([r['label'],e['event'],alias,loc,num(l['local_chirality']),num(f['normalized_ancestral_chirality']),num(f['chirality']),num(f['correspondence']),num(f['closure']),num(f['tension']),num(f['charge']),any(s['bias_structurally_reached'] for s in states),'; '.join(str(s['bias_generation']) for s in states),acts])
(P/'EVENT-TRACE.md').write_text('# Every proposition Write and Attestation event\n\nAll event targets are real service-created propositions. Creating Cycle/Phase belongs to the target proposition; current actor positions after the contribution appear in the field table. Full vote IDs/phase IDs and causal IDs are in candidate-trace.json; authors follow the unchanged actor/action schedule in the preserved history. Numeric values are printed to seven decimals; raw statistics retain full precision in JSON.\n\n## Event identities\n\n'+table(['Boundary','Event','Alias','Proposition ID','Parents','Domain','Creating Cycle/Phase','Effective votes'],[r[:8] for r in events])+'\n## Local observations\n\n'+table(['Boundary','Alias','O','O direction','Intervals','Count','Cadence maturity','Mean','SD','CV','AC','AC direction','Local Chirality'],[[r[0],r[2]]+r[8:] for r in events])+'\n## Field\n\nAncestral contribution is the normalized sum of the production inverse-square ancestry terms. The head also receives its denominator weight.\n\n'+table(['Boundary','Event','Alias','Current actors','Local Chirality','Ancestral contribution','Combined Chirality','Correspondence','Closure','Tension','Charge'],[r[:11] for r in projection])+'\n## Lifecycle\n\n'+table(['Boundary','Event','Alias','Current actors','Bias reached','Bias generation','Available actions'],[r[:4]+r[11:] for r in projection]))

allrows=[]
for r in d['boundaries']:
    for s in r['states']:
        if 'field' not in s:continue
        f=s['field'];allrows.append([r['label'],s['actor'],s['cycle'],s['phase'],name(s['gate_id']),*fieldsummary(f,True),s['grounding']['admissible'],s['bias_structurally_reached'],s['bias_generation'],s['four_way_overlap'],','.join(s['available_actions'])])
(P/'ALL-BOUNDARIES.md').write_text('# Every current participant state\n\nIncludes structural actions and Cycle entries without Attestation. Local snapshots and invariant results for **every existing proposition**, not just current Gates, are retained in candidate-trace.json.\n\n'+table(['Boundary','Actor','Cycle','Phase','Gate','O','Intervals','AC','O/AC directions','Chirality','Tension','Grounded','Bias reached','Bias generation','Four-way overlap','Actions'],allrows))

retained=[]
for ident,local in d['boundaries'][-1]['local_snapshots'].items():
    oldid=rev.get(ident);last=None
    for r in old['boundaries']:
        if r['label'] not in rows:continue
        for s in r['states']:
            if s.get('gate_id')==oldid:last=(r['label'],s)
    if last:
        label,s=last; own=rows[label]['local_snapshots'][ident]['current_history'];live=s['field']['current_history']
        retained.append([name(ident),label,seq(live['intervals']),seq(own['intervals']),len(live['act_ids']),len(own['act_ids']),','.join(x for x in live['act_ids'] if x not in {rev.get(y) for y in own['act_ids']})])
(P/'INTERVALS.md').write_text('# Which events belong to each proposition?\n\nEach comparison uses the same last recorded current-Gate boundary for that proposition. Excluded IDs are historical acts targeting ancestors rather than this proposition. Removing endpoints can combine the spacing between remaining own acts; the candidate is not a filter that merely deletes zero numbers from the old interval array. No same-position act is deduplicated.\n\n'+table(['Proposition','Compared boundary','v3 lineage intervals','Candidate own intervals','v3 acts','Own acts','Excluded ancestral act IDs'],retained))

checks=[]
for r in d['boundaries']:
    if r['created']['cycles'] and r['label']!='origin':
        for ident,x in r['invariants'].items():
            if x['entire_local_snapshot_unchanged'] and r['local_snapshots'][ident]['local_chirality']>0:
                checks.append({'type':'cycle_invariance','boundary':r['label'],'proposition':name(ident),'id':ident,'local':r['local_snapshots'][ident]});break
    if (r.get('input') or {}).get('action')=='proposition.reclassify':
        ident=r['input']['proposition_id'];checks.append({'type':'reclassify_parent_invariance','boundary':r['label'],'proposition':name(ident),'invariant':r['invariants'][ident],'local':r['local_snapshots'][ident]})
(P/'invariance-witnesses.json').write_text(json.dumps(checks,indent=2)+'\n')
print(json.dumps({'stop':d['stop'],'completed_actions':d['completed_actions'],'boundaries':len(d['boundaries']),'events':len(events),'actor_states':len(allrows),'four_way_overlaps':d['four_way_overlaps'],'ordinary_commits':d['ordinary_commits'],'focus_rows':len(comparison),'cycle_witnesses':sum(x['type']=='cycle_invariance' for x in checks)},indent=2))
