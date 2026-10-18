#!/usr/bin/env python3
"""Extract a private SQL capture into a text-redacted, isolated projection fixture.

Never imports account, credential, connection, membership, queue or auth tables.
Retains recorded domain rows/IDs and JSON body shape; replaces prose with ASCII
padding of equal JSON-encoded size (PHP's UNESCAPED_SLASHES convention).
Usage: python3 prepare-c3-snapshot.py source.sql destination.json.gz
"""
import gzip
import hashlib
import json
from pathlib import Path
import re
import sys

TABLES = '''substrates participants cycles phases propositions supersessions
attestations receipts instrument_executions participant_receipt_contexts
proposition_receipt_links receipt_consumptions receipt_context_events
context_horizons context_horizon_memberships substrate_ledger_entries
commitment_snapshots canonical_measurements chirality_observations
closure_births prospective_closure_states phase_profile_observations
local_chirality_states proposition_attestation_cursors proposition_measurements
commit_region_polarities commit_results reclassification_results
continuation_failures horizon_exploration_states horizon_failure_witnesses
origin_branches origin_branch_memberships potential_branch_regions
potential_entries potential_projection_states potential_region_dependencies
potential_region_states potential_dirty_horizons terminal_encounters tragedies
commit_region_exhaustions exhausted_region_memberships tragedy_final_attempts
tragedy_receipt_scopes reinforcements reinforcement_sources'''.split()


def read_sql(path):
    tables, table = {}, None

    def unescape(value):
        if value == r'\N':
            return None
        return re.sub(r'\\([0-7]{1,3}|x[0-9a-fA-F]{1,2}|.)', lambda m:
                      chr(int(m[1], 16)) if m[1].startswith('x') else
                      chr(int(m[1], 8)) if m[1][0].isdigit() else
                      {'n': '\n', 'r': '\r', 't': '\t', 'b': '\b', 'f': '\f', 'v': '\v'}.get(m[1], m[1]), value)

    for line in Path(path).read_text().splitlines():
        if table:
            if line == r'\.':
                table = None
            elif table in TABLES:
                values = [unescape(v) for v in line.split('\t')]
                assert len(values) == len(columns)
                tables[table].append(dict(zip(columns, values)))
        elif match := re.match(r'COPY public\.(\w+) \((.*?)\) FROM stdin;', line):
            table, columns = match[1], match[2].split(', ')
            if table in TABLES:
                tables[table] = []
    return tables


def padding(value):
    return None if value is None else 'x' * (len(json.dumps(value, ensure_ascii=True)) - 2)


def redacted_url(value):
    # Preserve validation and compact JSON byte size without retaining the host,
    # path or query. No network requests are made by the projection diagnostic.
    prefix = value.split('://', 1)[0] + '://x/'
    length = len(padding(value))
    assert length >= len(prefix)
    digest = hashlib.sha256(value.encode()).hexdigest()
    return prefix + (digest * ((length // len(digest)) + 1))[:length - len(prefix)]


def scrub(value, key=''):
    if isinstance(value, dict):
        return {k: scrub(v, k) for k, v in value.items()}
    if isinstance(value, list):
        return [scrub(v, key) for v in value]
    if isinstance(value, str):
        if value.startswith(('http://', 'https://')):
            return redacted_url(value)
        if key in {'reinforcement_version', 'validation', 'kind', 'type', 'status', 'direction', 'domain', 'register', 'tier', 'load_mode'}:
            return value
        if re.fullmatch(r'[0-9a-hjkmnp-tv-z]{26}', value) or re.match(r'^\d{4}-\d\d-\d\d[T ]', value):
            return value
        return padding(value)
    return value


def main(source, destination):
    tables = read_sql(source)
    for table, rows in tables.items():
        for ordinal, row in enumerate(rows):
            for key in ('statement', 'rationale', 'designation', 'callsign', 'title', 'description', 'failure_reason'):
                if key in row:
                    row[key] = padding(row[key])
                    if key == 'callsign' and row[key]:
                        # Unique pseudonyms preserve the substrate/callsign key
                        # as well as encoded size; never retain original names.
                        token = format(ordinal, 'x')
                        assert len(token) <= len(row[key])
                        row[key] = token.rjust(len(row[key]), 'x')
            if 'user_id' in row:
                row['user_id'] = None
            if table == 'substrates':
                row['slug'] = 'c3-' + row['id']
                row['configuration'] = '{}'
            if table in {'receipts', 'instrument_executions'}:
                if row.get('reference') and not re.fullmatch(r'[0-9a-hjkmnp-tv-z]{26}', row['reference']):
                    row['reference'] = scrub(row['reference'])
                for key in ('metadata', 'input'):
                    if row.get(key):
                        row[key] = json.dumps(scrub(json.loads(row[key])), separators=(',', ':'))
            if table == 'reinforcement_sources':
                row['resource'] = padding(row['resource'])
    artifact = {'source_sha256': hashlib.sha256(Path(source).read_bytes()).hexdigest(),
                'method': 'Recorded October 18 domain rows; prose redacted with equal compact JSON bytes. No accounts or credentials. Not a lifecycle replay.',
                'tables': tables}
    raw = json.dumps(artifact, separators=(',', ':')).encode()
    Path(destination).parent.mkdir(parents=True, exist_ok=True)
    Path(destination).write_bytes(gzip.compress(raw, mtime=0))
    print(json.dumps({'tables': {t: len(r) for t, r in tables.items()}, 'json_bytes': len(raw),
                      'gzip_bytes': Path(destination).stat().st_size}))


if __name__ == '__main__':
    main(*sys.argv[1:])
