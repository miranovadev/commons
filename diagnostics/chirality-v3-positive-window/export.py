import pathlib,gzip,base64
for name in ['window-trace.json','result-3-sparse_5.json']:
    p=pathlib.Path(__file__).parent/name
    if p.exists():
        print('WINDOW_FILE',p.name,base64.b64encode(gzip.compress(p.read_bytes(),mtime=0)).decode())
