<?php

// Fixed CLI worker. Only the reader supplies argv. Pattern and subject are untrusted data on stdin.
$expression = rtrim(fgets(STDIN), "\n");
$subject = stream_get_contents(STDIN);
if ($argc === 1) {
    // Preserve the v3 first-match contract, including zero-width matches.
    $result = preg_match($expression, $subject, $matches, PREG_OFFSET_CAPTURE);
    if ($result === false) {
        exit(2);
    }
    echo json_encode($result ? [$matches[0][1], strlen($matches[0][0])] : null, JSON_THROW_ON_ERROR);

    return;
}

$capacity = (int) $argv[1];
$include = (int) $argv[2];
$spans = [];
// PHP's global matcher advances zero-width matches on UTF-8 boundaries and retries a
// nonempty alternative at the same position. Store only bounded spans, never all captures.
$result = preg_replace_callback($expression, function (array $match) use (&$spans, $include): string {
    if (count($spans) < $include) {
        $spans[] = [$match[0][1], strlen($match[0][0])];
    }

    return '';
}, $subject, $capacity + 1, $count, PREG_OFFSET_CAPTURE);
if ($result === null) {
    exit(2);
}
// The extra match proves that enumeration stopped early. It is never reported as an exact total.
echo json_encode(['total' => $count <= $capacity ? $count : null, 'spans' => $spans], JSON_THROW_ON_ERROR);
