import pathlib, gzip, base64, json
for p in pathlib.Path(__file__).parent.glob('result-*.json'):
    print('AUTOPSY_FILE',p.name,base64.b64encode(gzip.compress(p.read_bytes())).decode())
    r=json.loads(p.read_text())
    print('AUTOPSY_SUMMARY',json.dumps({'case':r['case'],'actions':len(r['actions']),'stop':r['stop']}))
