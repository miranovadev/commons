import pathlib,gzip,base64,json
for p in pathlib.Path(__file__).parent.glob('result-*.json'):
 print('GROUNDING_FILE',p.name,base64.b64encode(gzip.compress(p.read_bytes())).decode())
 r=json.loads(p.read_text()); c=r.get('continuation',{})
 print('GROUNDING_SUMMARY',json.dumps({'stop':c.get('stop'),'actions':len(c.get('actions',[])),'commit':c.get('commit'),'error':c.get('error')}))
