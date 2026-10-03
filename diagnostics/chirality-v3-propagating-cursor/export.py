import pathlib, gzip, base64
for p in sorted(pathlib.Path(__file__).parent.glob('*.json')):
    print('CURSOR_FILE', p.name, base64.b64encode(gzip.compress(p.read_bytes(), mtime=0)).decode())
