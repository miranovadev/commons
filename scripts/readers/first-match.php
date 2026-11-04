<?php

// Fixed CLI worker. The first line is the validated PCRE expression. All remaining bytes are subject data.
$expression = rtrim(fgets(STDIN), "\n");
$subject = stream_get_contents(STDIN);
$result = preg_match($expression, $subject, $matches, PREG_OFFSET_CAPTURE);
if ($result === false) {
    exit(2);
}
echo json_encode($result ? [$matches[0][1], strlen($matches[0][0])] : null, JSON_THROW_ON_ERROR);
