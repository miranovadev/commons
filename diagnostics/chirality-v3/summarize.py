#!/usr/bin/env python3
"""Validate polarity equivalence and flatten diagnostic observations."""
import csv,json,math
from pathlib import Path
ROOT=Path(__file__).resolve().parent

def canonical(value):
    if isinstance(value,dict):return {k:canonical(v) for k,v in value.items() if k not in ('origin_label','gate_id')}
    if isinstance(value,list):return [canonical(v) for v in value]
    return value

rows=[];summary=[]
for name in 'ABCD':
    a=json.loads((ROOT/f'result-{name}-support.json').read_text())
    b=json.loads((ROOT/f'result-{name}-refute.json').read_text())
    assert canonical(a)==canonical(b), f'Polarity mismatch: {name}'
    for result in (a,b):
        for record in result['observations']:
            x=record['observation'];old=x['current'];new=x['candidate'];c=x['canonical']
            row={'case':name,'origin_label':result['origin_label'],'stage':record['stage'],'current_domain':x['domain'],
                 'current_lean':x['current_lean'],'previous_lean':x['previous_lean'],**c}
            for side in ('current','previous'):
                v=x[side+'_intervals']
                row.update({side+'_intervals':json.dumps(v['intervals']) if v else None,side+'_cv':v['cv'] if v else None,side+'_cv_status':v['status'] if v else 'no_previous_domain'})
            for k in ('orientation','attestation_cadence','local_chirality','attenuated_chirality'):
                row['v2_'+k]=old[k];row['v3_'+k]=new[k]
            row.update({'v2_orientation_direction':old['gate']['orientation_direction'],
                'v2_cadence_direction':old['gate']['attestation_cadence_direction'],'v2_chirality_direction':old['gate']['chirality_direction'],
                'v2_bias_admissible':old['gate']['admissible'],'v3_orientation_direction':new['orientation_direction'],
                'v3_cadence_direction':None,'v3_chirality_direction':None,'v3_bias_admissible':None,
                'v2_tension':round((c['correspondence']+old['attenuated_chirality']+c['closure'])/3,7),
                'v2_required_correspondence':-(old['attenuated_chirality']+c['closure']),
                'v3_tension':None,'v3_required_correspondence':None,'v3_resulting_domain':None,
                'v3_orientation_contribution':new['attenuated_orientation_contribution'],
                'v3_tension_upper_bound':round((c['correspondence']+c['closure']+new['chirality_range_if_all_cadences_bounded'][1])/3,7)})
            rows.append(row)
    summary.append({'case':name,'polarity_equivalent':True,'stop':a['stop'],'ordinary_commits':0,
                    'bias_gate':a['observations'][-1]['observation']})
with (ROOT/'traces.csv').open('w') as f:
    writer=csv.DictWriter(f,fieldnames=rows[0].keys(),lineterminator='\n');writer.writeheader();writer.writerows(rows)
(ROOT/'summary.json').write_text(json.dumps({'cases':summary,'zero_mean_cases':{
    'no_intervals':{'intervals':[],'cv':None},'all_zero':{'intervals':[0,0],'mean':0,'sigma':0,'cv':None},
    'constant_positive':{'intervals':[2,2],'mean':2,'sigma':0,'cv':0},'burst_then_entry':{'intervals':[0,1],'mean':.5,'sigma':.5,'cv':1}}},indent=2)+'\n')
print(f'{len(rows)} observation rows; all four polarity pairs exactly equivalent after removing generated Gate IDs.')
