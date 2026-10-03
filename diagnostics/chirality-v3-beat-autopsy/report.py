"""Render recorded production-service results; verify interval transforms, not field formulas."""
import json, pathlib, statistics, hashlib
p=pathlib.Path(__file__).parent
baseline=json.loads((p/'../chirality-v3-direction-autopsy/result-3-sparse_5.json').read_text())
history=baseline['final_states'][0]['field']['current_history']
acts={a['id']:a for a in baseline['persisted_attestations']}
cycles={a['id']:a for a in baseline['persisted_cycles']}
participants={a['actor']:cycles[a['cycle_id']]['participant_id'] for a in baseline['actions']}
phases={a['phase_id']:(a['actor'],a['cycle'],a['phase'],'p'+str(a['step'])) for a in baseline['actions']}
phases.update({a['before']['phase_id']:(a['actor'],a['before']['cycle'],a['before']['phase'],'c'+str(a['step'])) for a in baseline['continuation']['actions']})
nodes={}
for a in baseline['trace']:
    nodes[a['gate_id']]='Origin' if a['gate_tier']=='committal' else nodes.get(a['gate_id'],'')
c=baseline['continuation']['actions']
nodes.update({c[0]['before']['gate_id']:'D1',c[1]['before']['gate_id']:'P1',c[6]['before']['gate_id']:'Q1',c[9]['before']['gate_id']:'S1',c[11]['before']['gate_id']:'D2',c[15]['before']['gate_id']:'P2',c[17]['before']['gate_id']:'Q2',c[18]['before']['gate_id']:'S2',baseline['final_states'][0]['gate_id']:'R'})
events=[]
for i,id in enumerate(history['act_ids']):
    a=acts[id];actor,cycle,phase,step=phases[a['phase_id']]
    owning=next(v for v in cycles.values() if v['participant_id']==participants[actor] and v['sequence']==cycle)
    events.append({'event':'E'+str(i+1),'id':id,'target':a['proposition_id'],'target_label':nodes[a['proposition_id']],'actor':actor,'participant_id':participants[actor],'cycle':cycle,'cycle_id':owning['id'],'own_cycle_entry_order':owning['event_order'],'phase':phase,'phase_id':a['phase_id'],'action':step,'position':sum(v<history['act_order'][i] for v in history['represented_cycle_order']),'act_order':history['act_order'][i]})
zeros=[]
for i,gap in enumerate(history['intervals']):
    if gap:continue
    a,b=events[i:i+2]
    assert a['position']==b['position']
    zeros.append({'interval_index':i+1,'from':a,'to':b,'same_own_cycle':(a['actor'],a['cycle'])==(b['actor'],b['cycle']),'reason':'No represented Cycle creation event occurred strictly between the consecutive acts.'})
out=['# Every zero in the preserved direction-autopsy specimen','','These IDs identify the previous exact specimen. E labels are chronological within the final R lineage, not a change to persisted history. Position is the count of represented Cycle entries preceding the act, not the author\'s own Cycle number. Subtracting 3 gives a first-act-relative coordinate.','','| Event | Action | Attestation ID | Target | Participant / own Cycle / Phase | Position |','|---|---|---|---|---|---|']
for a in events:out.append(f"| {a['event']} | {a['action']} | {a['id']} | {a['target_label']} | A{a['actor']} / C{a['cycle']} / P{a['phase']} | {a['position']} |")
out+=['','| Target label | Exact proposition ID |','|---|---|']
for id,name in nodes.items():
    if name:out.append(f'| {name} | {id} |')
out+=['','| Interval index | Consecutive acts | Targets | Authors / own Cycles | Positions | Same owning Cycle? |','|---|---|---|---|---|---|']
for z in zeros:
    a,b=z['from'],z['to']
    out.append(f"| {z['interval_index']} | {a['event']} → {b['event']} | {a['target_label']} → {b['target_label']} | A{a['actor']} C{a['cycle']} → A{b['actor']} C{b['cycle']} | {a['position']} → {b['position']} | {'yes' if z['same_own_cycle'] else 'no'} |")
out+=['','Every row is zero for the same exact reason: the event-order filter finds **no represented Cycle-entry event strictly between the two acts**. All 12 pairs share a cumulative represented-entry position. Seven share an owning Cycle; five do not. The zero does not mean identical timestamps, duplicate acts, or necessarily the same participant/Cycle. All are genuine authored acts.']
(p/'ZEROS.md').write_text('\n'.join(out)+'\n')
(p/'zero-map.json').write_text(json.dumps({'source_sha256':hashlib.sha256((p/'../chirality-v3-direction-autopsy/result-3-sparse_5.json').read_bytes()).hexdigest(),'events':events,'zeros':zeros},indent=2)+'\n')
control_path=p/'../chirality-v3-natural-rhythm/result-3-regular_3.json'
control=json.loads(control_path.read_text())
o=next(x for x in control['observations'] if x['step']==32)
h=o['current_history'];pos=[sum(e<a for e in h['represented_cycle_order']) for a in h['act_order']]
assert len(pos)==len(set(pos))
gaps=[b-a for a,b in zip(pos,pos[1:])]
assert gaps==h['intervals']==[3,3]
(p/'control.json').write_text(json.dumps({'source_file':control_path.name,'source_sha256':hashlib.sha256(control_path.read_bytes()).hexdigest(),'step':32,'positions':pos,'A_intervals':h['intervals'],'B_intervals':gaps,'mean':statistics.mean(gaps),'stddev':statistics.pstdev(gaps),'cv':0.0,'cadence':o['attestation_cadence'],'note':'Existing lawful distinct-position prefix; no new history authored. Canonical matrix observation supplies A; interval identity establishes B equality.'},indent=2)+'\n')
if not (p/'beat-trace.json').exists():raise SystemExit('Zero mapping and control complete; runtime dual trace not yet available.')
trace=json.loads((p/'beat-trace.json').read_text())
for row in trace['rows']:
    a,b=row['A'],row['B']
    assert b['current_history']['intervals']==[v for v in a['current_history']['intervals'] if v>0]
    assert all(a[k]==b[k] for k in ['orientation','correspondence','closure'])
for kind in ['A','B']:
    out=[f'# Interpretation {kind}: full trace','','All selected pre-action boundaries, final boundaries, and changed proposition observations. B applies its interval stream at every first-Settlement lineage position, using production attenuation and projection. It never drives the scheduler or persists. `local Bias` is the existing causal-source regional eligibility plus the real PhaseGenerationService result on an unsaved measurement clone; no future result or Commit is claimed. `n/a` means not a current Bias boundary. Grounding remains the actual state.','','| Boundary | Source / observing actor-Cycle-Phase | Occupied positions | Intervals | n | Mean | Population SD | CV | Maturity | Cadence / direction | Orientation / direction | Chirality | Correspondence | Closure | Tension | Charge | Coherence | Local Bias | Vote / Receipt |','|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|']
    for row in trace['rows']:
        v=row[kind];h=v['current_history'];g=v['coherence'];gr=row['grounding']['requirements'];occupied=row['B']['current_history']['occupied_positions']
        data=[row['label'],row['proposition_id']+' / '+str((row['actor'],row['cycle'],row['phase'])),occupied,h['intervals'],h['usable_interval_count'],h['mean'],h['stddev'],h['cv'],v['cadence_status'],str(v['attestation_cadence'])+' / '+g['attestation_cadence_direction'],str(v['orientation'])+' / '+g['orientation_direction'],v['chirality'],v['correspondence'],v['closure'],v['tension'],v['charge'],g['admissible'],'n/a' if v['local_bias_source_admissible'] is None else v['local_bias_source_admissible'],str(gr['attestation'])+' / '+str(gr['receipt'])]
        out.append('| '+' | '.join(map(str,data))+' |')
    (p/('TRACE-'+kind+'.md')).write_text('\n'.join(out)+'\n')
print('Verified',len(trace['rows']),'dual observations;',len(zeros),'zero intervals; distinct-position control passed.')
