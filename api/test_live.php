<?php
$url = 'https://mapard.felipemiramontesr.net/api/investigate.php?auth=zero_day_wipe';
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: MAPARD-TEST\r\n"
    ],
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false,
    ]
];
$context = stream_context_create($opts);
$result = file_get_contents($url, false, $context);
echo "Result:\n" . $result . "\n";
