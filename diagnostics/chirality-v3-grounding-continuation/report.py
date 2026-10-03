"""Render recorded production-service output; no substrate formulas implemented here."""
import json
from pathlib import Path
D=Path(__file__).parent
r=json.loads((D/'result-3-sparse_5.json').read_text());c=r['continuation']
def v(x):
 if x is None:return '—'
 if isinstance(x,bool):return 'yes' if x else 'no'
 if isinstance(x,float):return f'{x:.7f}'
 if isinstance(x,(dict,list)):return json.dumps(x,separators=(',',':'))
 return str(x)
def t(h,rows):return '\n'.join(['| '+' | '.join(h)+' |','|'+'|'.join(['---']*len(h))+'|']+['| '+' | '.join(v(x).replace('|','/') for x in row)+' |' for row in rows])
def lifecycle(x):
 l=x['lifecycle'];p=l['plan'];return {'grounding':l['grounding'],'generation':l['generation'],'chirality_gate':l['chirality_gate'],'correspondence_gate':l['correspondence_gate'],'commit':l['commit'],'rest_reason':p.get('rest_reason')}
lines=['# Grounding continuation: complete runtime trace','',
 'Archived steps 0–32 are unchanged; continuation step 0 branches immediately before archived step 33. These step numbers are different namespaces. All new actions use ParticipationService. Existing-Gate fields are labelled separately from target fields and prospective/actual Commit measurements.', '',
 '## Fork: all participant plans','',t(['Actor','Cycle','Phase','Gate','Actions','Attested source','Receipt'],[[x['actor'],x.get('cycle'),x.get('phase'),x.get('gate_id'),x.get('lifecycle',{}).get('available_actions'),x.get('lifecycle',{}).get('grounding',{}).get('requirements',{}).get('attestation'),x.get('lifecycle',{}).get('grounding',{}).get('requirements',{}).get('receipt')] for x in c['fork_states']]),'',
 '## First genuine Attestation: same target before and after','']
first=c['actions'][0]
for key in ['target_before','target_after']:
 x=first[key];h=x['current_history'];lines+=[f'### {key}','',t(['Target','Intervals','n','Mean','SD','CV','O','Cadence','Mature','χ','C','Closure','T','Charge','Grounding'],[[x['id'],h['intervals'],h['usable_interval_count'],h['mean'],h['stddev'],h['cv'],x['orientation'],x['attestation_cadence'],x['attestation_cadence_mature'],x['chirality'],x['correspondence'],x['closure'],x['tension'],x['charge'],x['grounding']]])]
lines+=['','## Participant plans after the first Attestation','',t(['Actor','Cycle','Phase','Gate','Actions','Grounding','Chirality gate','Commit'],[[x['actor'],x.get('cycle'),x.get('phase'),x.get('gate_id'),x.get('lifecycle',{}).get('available_actions'),x.get('lifecycle',{}).get('grounding'),x.get('lifecycle',{}).get('chirality_gate'),x.get('lifecycle',{}).get('commit')] for x in first['all_states_after_first_attestation']]),'',
 '## Every continuation action','',t(['Step','Actor','Cycle','Phase','Action','Target/partner','Selection reason','Next actor Phase'],[[x['step'],x['actor'],x['before']['cycle'],x['before']['phase'],x['input']['action'],x['input'].get('proposition_id',x['input'].get('other_proposition_id')),x['why'],(x['after_actor']['phase'] or {}).get('number')] for x in c['actions']]),'',
 '## Existing-Gate field BEFORE each action','',t(['Step','Phase','Intervals','Mean','SD','CV','O','Cadence','Mature','χ','C','Closure','T','Charge','Current Domain'],[[x['step'],x['before']['phase'],x['before']['field']['current_history']['intervals'],x['before']['field']['current_history']['mean'],x['before']['field']['current_history']['stddev'],x['before']['field']['current_history']['cv'],x['before']['field']['orientation'],x['before']['field']['attestation_cadence'],x['before']['field']['attestation_cadence_mature'],x['before']['field']['chirality'],x['before']['field']['correspondence'],x['before']['field']['closure'],x['before']['field']['tension'],x['before']['field']['charge'],x['before']['domain']] for x in c['actions']]),'',
 '## Lifecycle BEFORE each action','',t(['Step','Gate','Available actions','Grounding','O/AC directions','Chirality gate','Commit','Rest reason'],[[x['step'],x['before']['gate_id'],x['before']['lifecycle']['available_actions'],x['before']['lifecycle']['grounding'],[x['before']['lifecycle']['chirality_gate']['orientation_direction'],x['before']['lifecycle']['chirality_gate']['attestation_cadence_direction']],x['before']['lifecycle']['chirality_gate']['admissible'],x['before']['lifecycle']['commit'],x['before']['lifecycle']['plan'].get('rest_reason')] for x in c['actions']]),'',
 '## Every genuine Attestation: measured target change','',t(['Step','Actor','Target','Before intervals','Before χ','Before T','After intervals','After Cadence','After χ','After T','After grounding'],[[x['step'],x['actor'],x['input']['proposition_id'],x['target_before']['current_history']['intervals'],x['target_before']['chirality'],x['target_before']['tension'],x['target_after']['current_history']['intervals'],x['target_after']['attestation_cadence'],x['target_after']['chirality'],x['target_after']['tension'],x['target_after']['grounding']] for x in c['actions'] if 'target_after' in x]),'',
 '## Stop and final readiness','',f"Stop: `{c['stop']}`; {len(c['actions'])} continuation actions.",'',t(['Actor','Status','Cycle','Phase','Actions','Grounding','Chirality gate','Commit'],[[x['actor'],x['status'],x.get('cycle'),x.get('phase'),x.get('lifecycle',{}).get('available_actions'),x.get('lifecycle',{}).get('grounding'),x.get('lifecycle',{}).get('chirality_gate'),x.get('lifecycle',{}).get('commit')] for x in c['final_states']]),'',
 '## Actual Commit snapshot','', '```json',json.dumps(c['commit'],indent=2),'```','']
for x in c['actions']:
 if 'prospective_commit' in x:lines+=['## Prospective Commit immediately before ordinary Commit','', '```json',json.dumps(x['prospective_commit'],indent=2),'```','']
lines+=['The raw archive also retains the unchanged prefix, exact timestamps/event ordering, all persisted acts/Cycles, complete schemas, receipt IDs, ancestry weights and stored measurement directions.']
(D/'TRACE.md').write_text('\n'.join(lines)+'\n')
