<?php
namespace Diagnostics\NaturalRhythm;
require __DIR__.'/../chirality-v3-natural-rhythm/NaturalRhythmTest.php';
class ProbeTest extends NaturalRhythmTest {
    protected function setUp(): void {
        parent::setUp();
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, function () { throw new \RuntimeException('Runtime probe stopped after 30 seconds; inspect partial trace, not a semantic finding.'); });
        pcntl_alarm(30);
    }
    protected function tearDown(): void {
        pcntl_alarm(0);
        parent::tearDown();
    }
}
