import base64, gzip, json
from pathlib import Path

root=Path(__file__).parent
results=[]
for p in sorted(root.glob('result-*.json')):
    r=json.loads(p.read_text())
    print('CASE',r['population'],r['case'],r['stop'],'actions',len(r['actions']),'commits',r['ordinary_commits'])
    compact={k:v for k,v in r.items() if k not in ['actions','bias','rows']}
    compact['rows']=[{k:v for k,v in row.items() if k not in ['morphology','v2','previous_history']} for row in r['rows']]
    compact['bias_count']=len(r['bias'])
    results.append(compact)
    # Lossless evidence transport through authenticated job logs; also uploaded as artifact.
    encoded=base64.b64encode(gzip.compress(p.read_bytes())).decode()
    print('DIAGNOSTIC_FILE',p.name,encoded)
print('MATRIX_JSON',json.dumps(results,separators=(',',':')))
if (root/'zero-mean-result.json').exists():
    print('ZERO_MEAN_JSON',(root/'zero-mean-result.json').read_text().replace('\n',''))
