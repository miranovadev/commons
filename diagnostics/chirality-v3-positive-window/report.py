"""Render runtime observations; never recalculate measurements or author history."""
import json
from pathlib import Path

ROOT = Path(__file__).parent
trace = json.loads((ROOT / 'window-trace.json').read_text())
replay = json.loads((ROOT / 'result-3-sparse_5.json').read_text())
old = json.loads((ROOT.parent / 'chirality-v3-grounding-continuation/result-3-sparse_5.json').read_text())
rows = trace['boundaries']
by = {r['label']: r for r in rows}
actions = replay['continuation']['actions']
names = {}
aliases = {1: 'Q1', 6: 'S1', 10: 'D2', 11: 'P2', 15: 'Q2', 17: 'S2', 18: 'R'}
names[by['origin']['states'][0]['gate_id']] = 'Origin'
names[by['continuation:0']['states'][1]['gate_id']] = 'P1'
names[actions[2]['after_actor']['plan']['gate_id']] = 'D1'
for n, name in aliases.items():
    names[by[f'continuation:{n}']['states'][actions[n]['actor']]['gate_id']] = name
phase = {1:'Distinguish',2:'Persist',3:'Constrain',4:'Disperse',5:'Mediate',6:'Bias',7:'Order'}
def alias(x): return names.get(x, x or '—')
def fmt(x): return f'{x:+.7f}' if isinstance(x,(float,int)) else str(x)
def seq(x): return '[' + ','.join(map(str,x)) + ']'
def component(f,k):
    d=f['directions']['orientation_direction' if k=='orientation' else 'attestation_cadence_direction']
    return f"{fmt(f[k])} / {d}"
def table(headers, data):
    return '\n'.join(['| '+' | '.join(headers)+' |','| '+' | '.join(['---']*len(headers))+' |']+['| '+' | '.join(str(x).replace('|','/').replace('\n',' ') for x in r)+' |' for r in data])+'\n'

mapping=[]
for ident,name in names.items():
    original = None
    if name=='P1': original=old['continuation']['actions'][1]['before']['gate_id']
    elif name=='D1': original=old['continuation']['actions'][2]['after_actor']['plan']['gate_id']
    elif name=='Origin': original=old['continuation']['actions'][2]['before']['gate_id']
    else:
        n=next(n for n,v in aliases.items() if v==name)
        original=old['continuation']['actions'][n]['after_actor']['plan']['gate_id']
    mapping.append([name,original,ident])
(ROOT/'IDENTITIES.md').write_text('# Proposition identities\n\nFresh replay IDs are mapped by the unchanged authored action sequence, not treated as original database IDs.\n\n'+table(['Alias','Archived specimen ID','This replay ID'],mapping))

path=[]
for n in range(1,19):
    a=actions[n]; actor=a['actor']; before=by[f'continuation:{n-1}']['states'][actor]; after=by[f'continuation:{n}']['states'][actor]
    b=before['field'];f=after['field'];created=by[f'continuation:{n}']['created']
    input_=a['input']; target=input_.get('proposition_id',input_.get('gate_id',before['gate_id']))
    path.append([n,f'A{actor} C{before["cycle"]}→C{after["cycle"]}',input_['action'],f'{phase[before["phase"]]}→{phase[after["phase"]]}',alias(target),alias(after['gate_id']),'/'.join(str(len(created[k])) for k in ['propositions','attestations','receipts','cycles']),f'{seq(b["current_history"]["intervals"])} → {seq(f["current_history"]["intervals"])}',f'{component(b,"attestation_cadence")} → {component(f,"attestation_cadence")}',f'{component(b,"orientation")} → {component(f,"orientation")}',f'{fmt(b["chirality"])} → {fmt(f["chirality"])}',f'{fmt(b["tension"])} → {fmt(f["tension"])}',f'{before["predicates"]["grounding"]["admissible"]} → {after["predicates"]["grounding"]["admissible"]}'])
(ROOT/'ACTION-PATH.md').write_text('# Earliest preserved path to Bias\n\nContinuation action numbers are zero-based. c0 is the genuine P1 grounding Attestation; this table begins after it. Each field pair follows the **same acting participant**, whose Gate can change. It is not a continuous measurement of one proposition. Input IDs, full schemas, new record IDs and all other participants are in the raw trace. New-record counts are propositions/Attestations/Receipts/Cycles; consuming or attaching an existing Receipt is not a new Receipt.\n\nThe 18-action prefix is the first actual prefix of this preserved schedule reaching Bias, not a proven globally shortest schedule. The structural dependency is Constrain Write → Disperse Write → Mediate Reconcile → Bias; Reconcile additionally needs a distinct eligible Disperse operand. No alternative history was authored or optimized.\n\n## Authored lifecycle\n\n'+table(['c','Actor/Cycle','Action','Phase','Selected source/target','Gate after','New P/A/R/C'],[r[:7] for r in path])+'\n## Measured effects\n\n'+table(['c','Intervals before→after','Cadence before→after','Orientation before→after','Chirality before→after','Tension before→after','Grounded before→after'],[[r[0]]+r[7:] for r in path]))

states=[]
for r in rows:
    for s in r['states']:
        if 'field' not in s: continue
        f=s['field'];p=s['predicates']
        states.append([r['label'],f'A{s["actor"]}',f'C{s["cycle"]}/{phase[s["phase"]]}',alias(s['gate_id']),s['gate_producing_phase'],seq(f['current_history']['intervals']),component(f,'orientation'),component(f,'attestation_cadence'),fmt(f['chirality']),fmt(f['correspondence']),fmt(f['closure']),fmt(f['tension']),fmt(f['charge']),p['grounding']['admissible'],p['reclassify_generation'],s['four_way_overlap'],', '.join(s['available_actions'])])
(ROOT/'ALL-BOUNDARIES.md').write_text('# All current participant boundaries\n\nOrigin + 33 replayed prefix actions + 80 preserved continuation actions. Each observation is post-action. Bias structurally reached means Phase 6 with a valid Phase-5 causal Gate; it does not mean its Reclassify Write is admissible. The generation column is the production reclassification opportunity check at Constrain/Bias, not a claim that every true opportunity is an offered action.\n\n'+table(['Boundary','Actor','Cycle/Phase','Gate','Producing phase','Intervals','Orientation/direction','Cadence/direction','Chirality','Correspondence','Closure','Tension','Charge','Grounded','Reclassify generation','Four-way overlap','Available actions'],states))

selected={}
for n in [0,1,3,4,6,8,17,18,19,21,33,34]:
    selected[f'continuation:{n}']=by[f'continuation:{n}']
(ROOT/'selected-boundaries.json').write_text(json.dumps(selected,indent=2)+'\n')
print(json.dumps({'boundaries':len(rows),'actor_states':len(states),'four_way_overlaps':trace['four_way_overlaps'],'new_counts_c1_to_c18':{k:sum(len(by[f'continuation:{n}']['created'][k]) for n in range(1,19)) for k in ['propositions','attestations','receipts','cycles']}},indent=2))
