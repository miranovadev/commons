# Natural Domain journeys

These are immutable natural probe captures, preserved as golden authoring sources
and richer manual/soak evidence. They are not autonomous acceptance tests.

- `solo-domain-natural.jsonl.xz`: 600 contributions, each with full before/input/after.
- `collective-domain-natural.jsonl.xz`: three entries and 630 contributions.
- `manifest.json`: lossless archive and original byte hashes.

Inspect with `xz -dc FILE` or Python's `lzma.open(FILE, 'rt')`. The fixed replay
fixtures are `tests/Replay/Fixtures/{solo,collective}-domain-cycle.json`.
The corresponding explicit manual invocations are:

```sh
php artisan test tests/Replay/ParticipationDomainCycleTest.php --filter=test_one_participant
php artisan test tests/Replay/ParticipationDomainCycleTest.php --filter=test_interleaved_participants
```

Run both explicitly with `composer test:replay-domain`. Neither replay is in the
Unit/Feature directories discovered by Core, Full Integration or `test:all`.
The fixture bytes, inputs and assertions are preserved. These scripts are optional
manual/reference/performance artifacts, not mandatory constitutional acceptance.

Domain-round-trip minimization is stopped. The focused invariant map is in
`docs/constitutional-integration.md`; runtime growth is documented in
`docs/constitutional-runtime-investigation.md` and deferred to separate work.
