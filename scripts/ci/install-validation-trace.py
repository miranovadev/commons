from pathlib import Path
root=Path('.')
helper=Path('.validation/tests/Support/PhaseProfileValidationTrace.php').read_text()
(root/'tests/Support/PhaseProfileValidationTrace.php').write_text(helper)
p=root/'tests/Fixtures/HorizonCycleFixture.php'
s=p.read_text().replace("            $state = $service->stateFor($participant);", "            $diagnosticStart = \\Tests\\Support\\PhaseProfileValidationTrace::start();\n            $state = $service->stateFor($participant);")
s=s.replace("            if ($action === 'proposition.commit') {", "            \\Tests\\Support\\PhaseProfileValidationTrace::finish($diagnosticStart, static::class, $state, $input);\n            if ($action === 'proposition.commit') {")
p.write_text(s)
p=root/'tests/Support/ParticipationHttpTestCase.php'
s=p.read_text().replace("        // A driver may use the GET it just performed for this same request.", "        $diagnosticStart = \\Tests\\Support\\PhaseProfileValidationTrace::start();\n        // A driver may use the GET it just performed for this same request.")
s=s.replace("        return $response->json('data');", "        \\Tests\\Support\\PhaseProfileValidationTrace::finish($diagnosticStart, static::class, $state, $input, $response->json('data'));\n        return $response->json('data');")
p.write_text(s)
