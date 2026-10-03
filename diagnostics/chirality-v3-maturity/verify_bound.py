#!/usr/bin/env python3
"""Independent arithmetic check of the singleton-reference Correspondence bound.
No production measurements are replaced by this verifier.
"""
import json, math, random
from collections import Counter,deque
from pathlib import Path

def ancestry(head,parents):
    seen=set();queue=[head]
    while queue:
        n=queue.pop()
        if n in seen:continue
        seen.add(n);queue.extend(p for p,s in parents.get(n,[]))
    return seen

def local(head,parents,labels,depths):
    ids=ancestry(head,parents)
    if head==0:return 1.0
    edges=[(p,t,s) for t in ids for p,s in parents.get(t,[]) if p in ids]
    def features(ids,edges,labels):
        current={i:labels[i] for i in ids};result=Counter()
        for iteration in range(3):
            for label in current.values():result[(iteration,label)]+=1
            nextlabels={}
            for i in ids:
                neighbors=[]
                for p,t,s in edges:
                    if p==i:neighbors.append(('out',s,current[t]))
                    if t==i:neighbors.append(('in',s,current[p]))
                nextlabels[i]=(current[i],tuple(sorted(neighbors,key=repr)))
            current=nextlabels
        return result
    a=features(ids,edges,labels);b=features({0},[],labels)
    cosine=sum(a[k]*v for k,v in b.items())/math.sqrt(sum(v*v for v in a.values())*sum(v*v for v in b.values()))
    topology=2*cosine-1
    cardinal=2/(depths[head]+1)-1
    assert topology<=2/math.sqrt(3)-1+1e-12
    return (topology+cardinal)/2

def measured(head,parents,raw,barriers):
    q=deque([(head,0)]);seen=set();total=mass=0
    while q:
        n,d=q.popleft()
        if n in seen:continue
        seen.add(n);w=1/(1+d)**2;mass+=w;total+=raw[n]*w
        if n==head or n not in barriers:q.extend((p,d+1) for p,s in parents.get(n,[]))
    return total/mass

rng=random.Random(20261003);maximum=-1;count=0
for trial in range(1000):
    size=rng.randint(3,24);parents={};depths={0:0};labels={0:'third'}
    for n in range(1,size):
        # Depth-one nodes have Origin as their only parent, as ordinary
        # Distinguish requires; arbitrary register labels strengthen the check.
        candidates=list(range(1,n))
        sources=[0] if not candidates or rng.random()<.2 else rng.sample(candidates,rng.randint(1,min(3,len(candidates))))
        parents[n]=[(p,rng.choice(['soft','hard'])) for p in sources]
        depths[n]=1+min(depths[p] for p in sources);labels[n]=rng.choice(['first','second','third'])
    raw={n:local(n,parents,labels,depths) for n in range(size)}
    assert all(raw[n]<0 for n in range(1,size))
    for head in range(1,size):
        if depths[head]<2:continue
        barriers={n for n in range(1,head) if depths[n]>=2 and rng.random()<.2}
        value=measured(head,parents,raw,barriers)
        assert value<0,(trial,head,value)
        maximum=max(maximum,value);count+=1

# Validate observed prospective morphologies belong to the proof's class.
root=Path(__file__).resolve().parent
observed=0
datasets=[json.loads(path.read_text()) for path in root.glob('result-*.json')]
if not datasets and (root/'actual-commit-morphologies.json').exists():
    datasets=[{'prospective_commits':[{'morphology':m} for m in json.loads((root/'actual-commit-morphologies.json').read_text())]}]
for data in datasets:
    for row in data['prospective_commits']:
        m=row.get('morphology')
        if m is None:continue
        nodes={n['id']:n for n in m['nodes']};positions=m['positions'];parents={i:[] for i in nodes}
        for e in m['edges']:
            assert e['source'] in nodes and e['target'] in nodes
            parents[e['target']].append(e['source'])
        roots=[i for i in nodes if not parents[i]]
        assert len(roots)==1
        origin=roots[0];assert positions[origin]['depth']==0
        for n in nodes:
            if positions[n]['depth']==1:assert set(parents[n])=={origin}
        assert len(ancestry(m['head'],{n:[(p,'unused') for p in ps] for n,ps in parents.items()}))==len(nodes)
        assert positions[m['head']]['depth']>=2
        observed+=1
result={'seed':20261003,'generated_dags':1000,'generated_commit_observations':count,'maximum_generated_correspondence':maximum,
        'observed_prospective_morphologies_in_class':observed,
        'topology_upper':2/math.sqrt(3)-1,'nonroot_depth_at_least_two_raw_upper':1/math.sqrt(3)-2/3,
        'two_node_raw_upper':1/math.sqrt(6)-.5,
        'depth_two_attenuation_numerator_upper':1/math.sqrt(3)-2/3+.25*(1/math.sqrt(6)-.5)+1/9,
        'depth_at_least_three_numerator_upper':1/math.sqrt(3)-1+.25+1/16}
(root/'bound-check.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result,indent=2))
