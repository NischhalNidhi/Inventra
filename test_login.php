<?php
$ch = curl_init('http://localhost/Inventra/public/index.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_POST, true);
// We need a valid session for CSRF token! So this might be complicated.
// Instead, if we inject a dummy CSRF token, we will get 302 Found.
// Wait! Let's bypass CSRF or simply fetch the page first to get a CSRF token.

curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');

// 1. GET page to extract CSRF token
$res1 = curl_exec($ch);
preg_match('/name="csrf_token" value="(.*?)"/', $res1, $matches);
$csrf = $matches[1] ?? '';

// 2. POST with CSRF token
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action' => 'login',
    'identifier' => 'manager@inventra.local',
    'password' => 'password',
    'csrf_token' => $csrf
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/x-www-form-urlencoded'
]);
$res2 = curl_exec($ch);
$info = curl_getinfo($ch);

echo "Status code: " . $info['http_code'] . "\n";
echo "Headers + Body:\n";
echo substr($res2, 0, 800);
