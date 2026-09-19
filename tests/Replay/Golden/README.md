# Natural Domain journeys

These are immutable natural probe captures, preserved as golden authoring sources
and richer manual/soak evidence. They are not autonomous acceptance tests.

- `solo-domain-natural.jsonl.xz`: 600 contributions, each with full before/input/after.
- `collective-domain-natural.jsonl.xz`: three entries and 630 contributions.
- `manifest.json`: lossless archive and original byte hashes.

Inspect with `xz -dc FILE` or Python's `lzma.open(FILE, 'rt')`. The fixed replay
fixtures are `tests/Fixtures/participation/{solo,collective}-domain-cycle.json`.
The corresponding explicit manual invocations are:

```sh
php artisan test tests/Feature/Api/ParticipationDomainCycleTest.php --filter=test_one_participant
php artisan test tests/Feature/Api/ParticipationDomainCycleTest.php --filter=test_interleaved_participants
```

They still occupy their existing suite locations until a validated minimal Core
replacement lands; these commands do not imply the suite split is complete.
See `docs/constitutional-runtime-investigation.md` for measured costs and status.
