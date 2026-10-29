#!/usr/bin/env python3
"""Read-only event-time provenance diagnostic for a PostgreSQL plain COPY dump.

No database connection or writes. Missing judgments are candidates for investigation,
not proof of lawful omitted actions; the export does not preserve action packets.
"""
import argparse
import collections
import json
import re


def unescape(value):
    if value == r'\N':
        return None
    escapes = {'t': '\t', 'n': '\n', 'r': '\r', 'b': '\b', 'f': '\f', 'v': '\v', '\\': '\\'}
    return re.sub(r'\\([0-7]{1,3}|.)', lambda m: chr(int(m[1], 8)) if re.fullmatch('[0-7]{1,3}', m[1]) else escapes.get(m[1], m[1]), value)


def inspect(path, slug):
    tables = collections.defaultdict(list)
    table = None
    with open(path) as stream:
        for line in stream:
            match = re.match(r'COPY public\.(\w+) \((.*?)\) FROM stdin;', line)
            if match:
                table, columns = match[1], match[2].split(', ')
            elif line.rstrip('\n') == r'\.':
                table = None
            elif table:
                tables[table].append(dict(zip(columns, map(unescape, line.rstrip('\n').split('\t')), strict=True)))
    keyed = {name: {r['id']: r for r in rows} for name, rows in tables.items() if rows and 'id' in rows[0]}
    substrate = next(r for r in tables['substrates'] if r['slug'] == slug)['id']
    actors = {r['id'] for r in tables['participants'] if r['substrate_id'] == substrate}
    phases, cycles, nodes = (keyed[n] for n in ('phases', 'cycles', 'propositions'))
    owner = lambda phase: cycles[phases[phase]['cycle_id']]['participant_id']
    votes = collections.defaultdict(list)
    replaced = {}
    for vote in tables['attestations']:
        votes[vote['proposition_id']].append(vote)
        if vote['supersedes_attestation_id']:
            replaced[vote['supersedes_attestation_id']] = vote['created_at']
    parents, retired = collections.defaultdict(list), collections.defaultdict(list)
    for edge in tables['supersessions']:
        parents[edge['target_proposition_id']].append(edge)
        if edge['type'] != 'variant':
            retired[edge['source_proposition_id']].append(edge['created_at'])
    events = [e for e in tables['exploration_events'] if owner(e['phase_id']) in actors]
    foreign, personal_heads, candidates = 0, 0, []
    for event in events:
        actor, at = owner(event['phase_id']), event['recorded_at']
        witness = json.loads(event['witness'])
        foreign_witness = owner(keyed['attestations'][witness['attestation_id']]['phase_id']) != actor
        foreign += foreign_witness
        def personal(node):
            return [v['id'] for v in votes[node] if owner(v['phase_id']) == actor and v['created_at'] <= at
                    and (v['id'] not in replaced or replaced[v['id']] > at)]
        personal_heads += bool(personal(event['proposition_id']))
        cycle = phases[event['phase_id']]['cycle_id']
        encounter = {int(p['kind']): p for p in tables['phases'] if p['cycle_id'] == cycle}
        arrivals = {p['gate_proposition_id']: encounter[k - 1]['gate_proposition_id'] for k, p in encounter.items()
                    if k - 1 in encounter and encounter[k - 1]['completed_at'] is not None}
        route, missing = [], []
        node = event['proposition_id']
        while node and node not in route:
            row = nodes[node]
            if row['tier'] == 'committal':
                break
            if cycles[phases[row['phase_id']]['cycle_id']]['entry_committal_id'] != cycles[cycle]['entry_committal_id']:
                break
            route.append(node)
            historical = any(t <= at for t in retired[node])
            if not historical and not personal(node):
                missing.append(node)
            incoming = [e['source_proposition_id'] for e in parents[node] if e['created_at'] <= at]
            node = incoming[0] if len(incoming) == 1 else arrivals.get(node, phases[row['phase_id']]['gate_proposition_id'])
            if incoming and node not in incoming:
                raise ValueError('Ambiguous event-time route')
        if foreign_witness or missing:
            candidates.append({'phase_id': event['phase_id'], 'head_id': event['proposition_id'],
                'foreign_witness': foreign_witness, 'personal_head_attestations': personal(event['proposition_id']),
                'selected_route': route, 'active_missing_personal_judgment_candidates': missing})
    return {'substrate': slug, 'events': len(events), 'foreign_witnesses': foreign,
        'events_with_effective_personal_head_judgment': personal_heads,
        'events_with_route_judgment_candidates': sum(bool(r['active_missing_personal_judgment_candidates']) for r in candidates),
        'examples': candidates[:3],
        'limits': 'Event-time recorded edges and effective judgments only. No action-packet or evidence-eligibility replay; no finding of premature completion inferred. Stage 2 acquisition did not yet apply.'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('dump')
    parser.add_argument('--slug', default='personal-exploration-offsets-attempt-2')
    args = parser.parse_args()
    print(json.dumps(inspect(args.dump, args.slug), indent=2))
