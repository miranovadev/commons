#!/usr/bin/env python3
import csv,json
from pathlib import Path
P=Path(__file__).resolve().parent
s=json.loads((P/'result-support.json').read_text());r=json.loads((P/'result-refute.json').read_text())

def normalized(x):
    if isinstance(x,list):return [normalized(v) for v in x]
    if isinstance(x,dict):return {k:normalized(v) for k,v in x.items() if k not in ('origin_label','direction','entry_committal_id','actual_proposition_id','history_proposition_id','morphology')}
    return x
assert s['stop']==r['stop']=='structural_bound_verified_three_commits'
assert normalized(s)==normalized(r),'Target-frame polarity equivalence failed'
rows=[];summary=[]
for data in [s,r]:
    actuals=[]
    for i,x in enumerate(data['prospective_commits']):
        k=x['closure_observation'];row={'origin_label':data['origin_label'],'observation':i+1,'step':x['step'],'next_commit_ordinal':x['ordinal'],
            'current_domain':x['current_domain'],'correspondence':x['correspondence'],'shape_topology':x['shape_topology'],'cardinal_position':x['cardinal_position'],
            'current_lean':x['current_lean'],'previous_lean':x['previous_lean'],'orientation':x['orientation'],'orientation_direction':x['orientation_direction'],
            'current_intervals':json.dumps(x['current_history']['intervals']),'previous_intervals':json.dumps(x['previous_history']['intervals']),
            'current_cv':x['current_history']['cv'],'previous_cv':x['previous_history']['cv'],
            'attestation_cadence':x['attestation_cadence'],'attestation_cadence_direction':x['attestation_cadence_direction'],
            'attestation_cadence_mature':x['attestation_cadence_mature'],'neutral_cadence_contribution':x['cadence_contribution'],
            'local_chirality':x['local_chirality'],'attenuated_chirality':x['chirality'],'chirality_direction':x['chirality_direction'],
            'closure':x['closure'],'committed_cardinal_position':k['committed_cardinal_position'],'committal_cadence':k['cadence'],
            'committed_cardinal_direction':k['committed_cardinal_position_direction'],'committal_cadence_direction':k['cadence_direction'],
            'closure_admissible':k['admissible'],'domain_depths':json.dumps(k['domain_depths']),
            'tension':x['tension'],'required_correspondence':x['required_correspondence'],'correspondence_margin':x['correspondence_margin'],
            'bias_component_gate':x['component_gate'],'predicted_domain':x['resulting_domain'],'commit_action_available':x['commit_action_available'],
            'actual_commit':'actual' in x,'actual_domain':x.get('actual',{}).get('direction_after'),
            'v2_orientation':x['v2']['orientation'],'v2_cadence':x['v2']['attestation_cadence'],
            'v2_local_chirality':x['v2']['local_chirality'],'v2_attenuated_chirality':x['v2']['attenuated_chirality']}
        rows.append(row)
        if 'actual' in x:actuals.append({k:v for k,v in x.items() if k not in ('morphology','entry_committal_id','actual_proposition_id')})
    summary.append({'origin_label':data['origin_label'],'origin':data['origin'],'stop':data['stop'],'actions':len(data['actions']),
        'ordinary_commits':data['commits'],'crossover':data['crossed'],'prospective_observations':len(data['prospective_commits']),
        'bias_observations':len(data['bias_observations']), 'bias_writes':sum(a['action']=='proposition.reclassify' and a['phase']==6 for a in data['actions']),
        'mature_cadence_observed':any(x['observation']['attestation_cadence_mature'] for x in data['bias_observations']),
        'actual_commit_observations':actuals})
with (P/'prospective-commits.csv').open('w') as f:
    w=csv.DictWriter(f,fieldnames=rows[0],lineterminator='\n');w.writeheader();w.writerows(rows)
(P/'summary.json').write_text(json.dumps({'polarity_equivalent':True,'runs':summary},indent=2)+'\n')
(P/'actual-commit-morphologies.json').write_text(json.dumps([x['morphology'] for x in s['prospective_commits'] if 'actual' in x],indent=2)+'\n')
print(json.dumps({'polarity_equivalent':True,'rows':len(rows),'runs':[{k:v for k,v in x.items() if k not in ('origin','actual_commit_observations')} for x in summary]},indent=2))
