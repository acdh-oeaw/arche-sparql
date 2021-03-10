<?php

/**
 * The MIT License
 *
 * Copyright 2017 Austrian Centre for Digital Humanities at the Austrian Academy of Sciences
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

require_once __DIR__ . '/vendor/autoload.php';

//$baseUrl = 'http://193.170.85.102:8080';
//$hostHeader = 'triplestore-parthenos-cached.acdh-dev.oeaw.ac.at';
$baseUrl = $_SERVER["TRIPLESTORE_URL"];
$hostHeader = $_SERVER["TRIPLESTORE_HOST_HEADER"] ?? parse_url($baseUrl,  PHP_URL_HOST);
$skipResponseHeaders = ['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'transfer-encoding', 'upgrade', 'host'];
$cacheDir = __DIR__ . '/cache';
$cacheValid = $_SERVER["CACHE_TIMEOUT"] ?? 7*24*60*60; // in seconds
$credentials = [
  '/' . $_SERVER["DB_NAME"] => $_SERVER["DB_USER"] . ':' . $_SERVER["DB_PASSWORD"],
];

$logFile = __DIR__ . '/log.csv';

// check if the request is safe
$authHeader = null;
$method = filter_input(\INPUT_SERVER, 'REQUEST_METHOD');
$deny   = false;
$deny   |= !in_array($method, ['GET', 'POST']);
if ($method === 'POST') {
    $allowedCT = ['multipart/form-data', 'application/x-www-form-urlencoded'];
    $cT = explode(';', filter_input(\INPUT_SERVER, 'CONTENT_TYPE') ?? '')[0];
    $deny      |= !in_array($cT, $allowedCT);
    $deny      |= filter_input(\INPUT_POST, 'query') === null && filter_input(\INPUT_POST, 'mapgraph') === null;
    $deny      |= filter_input(\INPUT_POST, 'update') !== null;
    $deny      |= filter_input(\INPUT_POST, 'updatePost') !== null;
    $deny      |= filter_input(\INPUT_POST, 'uri') !== null;
}

if (!$deny) {
    foreach($credentials as $path => $i) {
        if (preg_match('|^' . $path . '|', filter_input(\INPUT_SERVER, 'REQUEST_URI'))) {
            $authHeader = 'Basic ' . base64_encode($i);
        }
    }
}
// caching
$hash = sha1(filter_input(\INPUT_SERVER, 'REQUEST_URI') . filter_input(\INPUT_SERVER, 'QUERY_STRING') . json_encode($_POST));
$path = $cacheDir . '/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
if (!$deny && file_exists($path) && time() - filemtime($path) <= $cacheValid) {
    $headers = explode("\n", file_get_contents($path . 'headers'));
    http_response_code(array_shift($headers));
    foreach ($headers as $h => $v) {
         header($v);
    }
    header('X-Cache-Hit: 1');
    readfile($path);
    exit();
}

if (!file_exists(dirname($path))) {
    mkdir(dirname($path), 0750, true);
}

// prepare proxy headers
$headers = [];
foreach ($_SERVER as $k => $v) {
    if (substr($k, 0, 5) !== 'HTTP_') {
        continue;
    }
    $k = str_replace('_', '-', strtolower(substr($k, 5)));
    $headers[$k] = $v;
}
$contentType = filter_input(\INPUT_SERVER, 'CONTENT_TYPE');
if ($contentType !== null) {
    $headers['content-type'] = $contentType;
}
$contentLength = filter_input(\INPUT_SERVER, 'CONTENT_LENGTH');
if ($contentLength !== null) {
    $headers['content-length'] = $contentLength;
}
if ($authHeader !== null) {
    $headers['authorization'] = $authHeader;
}
if (isset($_SERVER['PHP_AUTH_USER'])) {
    $headers['authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
}
$headers['host'] = $hostHeader;

// prepare a proxy request
$method = strtoupper(filter_input(INPUT_SERVER, 'REQUEST_METHOD'));
$input  = null;
if ($method !== 'TRACE' && (isset($headers['content-type']) || isset($headers['content-length']))) {
    $input = fopen('php://input', 'r');
}
$options                    = [];
$output                     = fopen($path, 'w');
$options['sink']            = $output;
$options['verify']          = false;
$options['allow_redirects'] = true;
$options['http_errors']     = false;
$options['on_headers'] = function(\GuzzleHttp\Psr7\Response $response) {
    global $skipResponseHeaders, $path, $t;
    $t = microtime(true) - $t;
    $headers = $response->getStatusCode()  . "\n";
    foreach ($response->getHeaders() as $name => $values) {
        if (in_array(strtolower($name), $skipResponseHeaders)) {
            continue;
        }
        foreach ($values as $value) {
            $headers .= $name . ': '. $value . "\n";
        }
    }
    file_put_contents($path . 'headers', rtrim($headers));
};

$client                     = new \GuzzleHttp\Client($options);
$url = $baseUrl . filter_input(\INPUT_SERVER, 'REQUEST_URI');
header("X-DEBUG: ".json_encode([$method, $url, $headers]));
$request = new \GuzzleHttp\Psr7\Request($method, $url, $headers, $input);

// run the proxy request
$t = microtime(true); // updated in the headers handler function
try {
    $response = $client->send($request);
} catch (\GuzzleHttp\Exception\RequestException $e) {
    if ($e->hasResponse()) {
        $response = $e->getResponse();
    }
}
if ($input) {
    fclose($input);
}
fclose($output);

$headers = explode("\n", file_get_contents($path . 'headers'));
$status = array_shift($headers);
foreach ($headers as $h => $v) {
    header($v);
}
readfile($path);
if ((int) $status !== 200) {
    unlink($path);
    unlink($path . 'headers');
}
if (!empty($logFile)) {
    $log = fopen($logFile, 'a');
    fputcsv($log, [date('Y-m-d H:m:s'), $status, $t, filter_input(\INPUT_POST, 'query') ?? filter_input(\INPUT_GET, 'query')], ';', '"', '"');
    fclose($log);
}
