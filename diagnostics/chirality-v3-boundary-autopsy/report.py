"""Render recorded PHP output; this script does not calculate substrate values."""
import json
from pathlib import Path
D=Path(__file__).parent
N={1:'Distinguish',2:'Persist',3:'Constrain',4:'Disperse',5:'Mediate',6:'Bias',7:'Order'}
def v(x):
 if x is None:return '—'
 if isinstance(x,bool):return 'yes' if x else 'no'
 if isinstance(x,float):return f'{x:.7f}'
 if isinstance(x,(dict,list)):return json.dumps(x,separators=(',',':'))
 return str(x)
def table(h,rows):return '\n'.join(['| '+' | '.join(h)+' |','|'+'|'.join(['---']*len(h))+'|']+['| '+' | '.join(v(x).replace('|','/') for x in r)+' |' for r in rows])
def ident(x):return f"{x.get('step','final')}: A{x['actor']} C{x['cycle']} P{x['phase']}"
def reason(x):
 l=x['lifecycle']; p=l['plan']; out=[]
 if x['phase']==7:out+=l['commit'].get('reasons',[])
 if x['phase'] in [3,6]:
  s=l['reclassification_source']
  out+=s['grounding']['reasons']
  if s['existing_classification_in_write_register']:out+=['classification already exists']
  if not s['authorized_receipt_ids']:out+=['no authorized Cycle-entry receipt']
  if not s['accepts_gate']:out+=['wrong Gate position']
  if x['phase']==6:
   if x['gate_id'] not in l['bias']['operational']:out+=['acting Gate not operational Bias source']
   if not l['chirality_gate']['admissible']:out+=[l['chirality_gate']['reason']]
 if x['phase']==5:
  if not l['correspondence_gate']['admissible']:out+=[l['correspondence_gate']['reason']]
  elif not any(g['id'] != x['gate_id'] for g in p.get('gate_options',[])):out+=['no distinct eligible Reconcile partner']
 if p.get('rest_reason'):out+=['plan: '+p['rest_reason']]
 return '; '.join(out) or 'see full plan/options in JSON'
for file in sorted(D.glob('result-*.json')):
 r=json.loads(file.read_text()); trace=r['trace']; lines=[f"# Boundary trace: {r['population']} author(s), {r['case']}",'',
 'Zero-based action steps. Each row is BEFORE the selected action. Actor/Cycle sequences are local; Origin is actor 0 Cycle 1. P1 Distinguish, P2 Persist, P3 Constrain, P4 Disperse, P5 Mediate, P6 Bias, P7 Order.', '',
 'All current Domains remain Settlement. An existing-Gate positive sign is not an executed transition or a prospective Commit measurement. Directions below are retained production measurement directions; raw components are independently recomputed at the boundary. Numeric Cadence zero and false maturity remain separate.', '',
 '## Origin', '', '```json',json.dumps(r['origin'],indent=2),'```','',
 '## A1. Rhythm and component state','',table(['Boundary','Intervals','n','Mean','SD','CV','O','O dir','Mature','AC','AC dir','Local χ'],[
 [ident(x),x['field']['current_history']['intervals'],x['field']['current_history']['usable_interval_count'],x['field']['current_history']['mean'],x['field']['current_history']['stddev'],x['field']['current_history']['cv'],x['field']['orientation'],(x['field']['stored_measurement'] or {}).get('orientation_direction'),x['field']['attestation_cadence_mature'],x['field']['attestation_cadence'],(x['field']['stored_measurement'] or {}).get('attestation_cadence_direction'),x['field']['local_chirality']] for x in trace]),'',
 '## A2. Field state','',table(['Boundary','C','Shape/cardinal','Shape/cardinal dirs','Attenuated χ','Closure','Closure diagnostics','Tension','Charge','Sign-implied Domain'],[
 [ident(x),x['field']['correspondence'],list(x['field']['correspondence_components'].values()),[x['lifecycle']['correspondence_gate']['shape_topology_direction'],x['lifecycle']['correspondence_gate']['cardinal_position_direction']],x['field']['chirality'],x['field']['closure'],{k:x['field']['closure_details'].get(k) for k in ['depth','cadence','depth_direction','cadence_direction','admissible']},x['field']['tension'],x['field']['charge'],x['field']['sign_implied_domain']] for x in trace]),'',
 '## B. Lifecycle and selected actions','',table(['Boundary','Gate ID','Gate produced at','Available actions','Selected','Effective Attestation','Receipt','Chirality gate','Correspondence gate','Commit eligible','Concrete checks'],[
 [ident(x),x['gate_id'],N[x['gate_produced_at_phase']],x['lifecycle']['available_actions'],x['selected']['action'],x['lifecycle']['grounding']['requirements']['attestation'],x['lifecycle']['grounding']['requirements']['receipt'],x['lifecycle']['chirality_gate']['admissible'],x['lifecycle']['correspondence_gate']['admissible'],x['lifecycle']['commit']['admissible'],reason(x)] for x in trace]),'',
 '## Read-only mature-Cadence sensitivity at every boundary','',
 'Head-only substitutes the eventual observed +1 at the current measured head, leaving every ancestor, Orientation, Correspondence and Closure unchanged. The broader sensitivity substitutes +1 on every first-Settlement lineage node while preserving constitutional Origin. Neither changes stored values or action selection. Origin-head substitutions are mechanically exported but are not meaningful developmental counterfactuals.', '',
 table(['Boundary','Actual χ','Actual T','+1 head χ','+1 head T','+1 all Settlement χ','+1 all Settlement T'],[
 [ident(x),x['field']['chirality'],x['field']['tension'],x['counterfactuals']['cadence_plus_one_head_only']['chirality'],x['counterfactuals']['cadence_plus_one_head_only']['tension'],x['counterfactuals']['cadence_plus_one_all_first_settlement_nodes_sensitivity']['chirality'],x['counterfactuals']['cadence_plus_one_all_first_settlement_nodes_sensitivity']['tension']] for x in trace]),'',
 '## Cycle entries caused by ordinary participation','',table(['Step','Actor','Action','Entries added','Event order'],[[x['step'],x['actor'],x['caused_by'],x['entries_added'],x['event_order']] for x in r['cycle_boundaries']]),'',
 '## Exact Attestation occurrences','',table(['ID','Proposition','Phase ID','Direction','Event order','Timestamp (not interval unit)'],[[x['id'],x['proposition_id'],x['phase_id'],x['direction'],x['event_order'],x['timestamp']] for x in r['persisted_attestations']]),'',
 '## Final readiness (100-action archive boundary)','',table(['Actor','Status','Cycle','Phase','Actions','Reason'],[[x['actor'],x['status'],x.get('cycle'),x.get('phase'),x.get('lifecycle',{}).get('available_actions'),reason(x) if 'lifecycle' in x else x['state']] for x in r['final_states']]),'',
 'The full JSON retains every schema, option list, input target, receipt ID, stored measurement, ancestry weight and admissibility result. This is a bounded archived history, not an exhaustive reachability proof.']
 if r['rows']:
  lines+=['','## Prospective Commit calculations (separate scope)','',table(['Step','Cycle','Intervals','C','χ','Closure','T','Charge','Result','Eligibility reasons'],[[x['step'],x['cycle'],x['current_history']['intervals'],x['correspondence'],x['chirality'],x['closure'],x['tension'],x['charge'],x['resulting_domain'],x['plan'].get('commitment',{}).get('reasons')] for x in r['rows']])]
 (D/f"TRACE-{r['population']}-{r['case']}.md").write_text('\n'.join(lines)+'\n')
