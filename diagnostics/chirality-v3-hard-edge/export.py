import pathlib,gzip,base64
p=pathlib.Path(__file__).parent/'hard-edge.json'
if p.exists(): print('HARD_EDGE_FILE',p.name,base64.b64encode(gzip.compress(p.read_bytes(),mtime=0)).decode())
