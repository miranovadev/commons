import pathlib, gzip, base64
p = pathlib.Path(__file__).parent / 'candidate-trace.json'
if p.exists():
    print('LOCAL_FILE', p.name, base64.b64encode(gzip.compress(p.read_bytes(), mtime=0)).decode())
