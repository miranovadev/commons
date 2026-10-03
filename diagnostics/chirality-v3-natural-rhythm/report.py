"""Build a reviewable report from recorded runtime results; never simulate fields."""
import json
from pathlib import Path

root=Path(__file__).parent
rs=[json.loads(p.read_text()) for p in sorted(root.glob('result-*.json'))]
def val(x):
    if x is None: return 'unavailable'
    if isinstance(x,float): return f'{x:.7f}'
    if isinstance(x,(list,dict)): return json.dumps(x,separators=(',',':'))
    return str(x)
def table(headers, rows):
    return '\n'.join(['| '+' | '.join(headers)+' |','| '+' | '.join(['---']*len(headers))+' |']+['| '+' | '.join(val(x).replace('|','/') for x in row)+' |' for row in rows])
lines=['# Natural Cycle-entry rhythm: runtime evidence','',
    'Base candidate: `c83a48b441f08177f60c7e85968ce0f703af11dd`. Results are bounded authored histories, not a global reachability proof.',
    '', '## Matrix outcomes','',table(['Population','Case','Stop','Actions attempted','Ordinary acts persisted','Commits','Crossed','Final Cycles'],
    [[r['population'],r['case'],r['stop'],len(r['actions']),r['actual_ordinary_attestations'],r['ordinary_commits'],r['crossed'],r['final_cycles']] for r in rs])]
for r in rs:
    lines += ['',f"## {r['population']} author(s): {r['case']}",'',
        'Requested scheduling gaps: `'+val(r['requested_rhythm'])+'`. Actual per-lineage intervals below are authoritative.',
        '', 'Participation: `'+val(r['participation'])+'`. Spacing shortfalls: '+str(len(r['spacing_shortfalls']))+'.',
        '', 'Origin: `'+val(r['origin'])+'`.']
    if r.get('semantic_boundary'): lines += ['', '**Semantic stop:** `'+r['semantic_boundary']+'`.']
    lines += ['', '### Prospective Commit observations','']
    if not r['rows']: lines += ['No prospective Commit reached.']
    else:
        headers=['Step','Actor','Cycle','Commit ordinal','Intervals','Count','Mean','SD','CV','Orientation','O direction','Cadence','Mature','Cadence direction','Chirality','Correspondence','Closure','Tension','Charge','Current Domain','Predicted Domain','Commit available','Actual Domain']
        rows=[]
        for x in r['rows']:
            h=x['current_history']
            rows.append([x['step'],x['actor'],x['cycle'],x['ordinal'],h['intervals'],len(h['intervals']),h['mean'],h.get('stddev'),h['cv'],x['orientation'],x['orientation_direction'],x['attestation_cadence'],x['attestation_cadence_mature'],x['attestation_cadence_direction'],x['chirality'],x['correspondence'],x['closure'],x['tension'],x['charge'],x['current_domain'],x['resulting_domain'],x['commit_action_available'],x.get('actual',{}).get('direction_after')])
        lines += [table(headers,rows)]
    lines += ['', '### Existing-Gate observations before acts / Order actions','',
        'These are existing positions, not hypothetical Commits or executed Domain transitions. Numeric zero with immature Cadence is neutral contribution, not observed rhythm.', '']
    obs=[]
    for x in r.get('observations',[]):
        h=x['current_history']; obs.append([x['step'],x['actor'],x['cycle'],x['phase'],h['intervals'],h['mean'],h.get('stddev'),h['cv'],x['orientation'],x['attestation_cadence'],x['attestation_cadence_mature'],x['chirality'],x['correspondence'],x['closure'],x['tension'],x['charge'],x['domain']])
    lines += [table(['Step','Actor','Cycle','Phase','Intervals','Mean','SD','CV','Orientation','Cadence','Mature','Chirality','Correspondence','Closure','Tension','Charge','Domain'],obs)]
    lines += ['', '### Cycle-entry boundaries','',table(['Step','Actor','Caused by','Entries','Event order'],[[x['step'],x['actor'],x['caused_by'],x['entries_added'],x['event_order']] for x in r['cycle_boundaries']])]
z=root/'zero-mean-result.json'
if z.exists():
    lines += ['', '## Isolated zero-mean runtime','', '```json',z.read_text().strip(),'```']
(root/'RESULTS.md').write_text('\n'.join(lines)+'\n')
