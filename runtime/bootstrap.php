<?php

ini_set('display_errors', '1');
error_reporting(E_ALL);

$showEnv = $_ENV;
$showEnv['AWS_SESSION_TOKEN'] = '[omitted]';
$showEnv['AWS_SECRET_ACCESS_KEY'] = '[omitted]';
$showEnv['AWS_ACCESS_KEY_ID'] = '[omitted]';

echo "Environment: " . json_encode($showEnv, JSON_PRETTY_PRINT) . "\n";

define('LAMBDA_API_BASE', getenv('AWS_LAMBDA_RUNTIME_API'));

$requestId = null;
set_exception_handler('lambdaInitFail');

function lambdaInitFail(\Throwable $e = null)
{
    $err = json_encode([
        'errorMessage' => $e ? $e->getMessage() : 'Generic failure',
        'errorType' => $e ? get_class($e) : 'Internal',
        'trace' => $e ? $e->getTraceAsString() : null
    ], JSON_PRETTY_PRINT);

    echo $err . "\n";

    file_get_contents(
        "http://" . LAMBDA_API_BASE . "/2018-06-01/runtime/init/error",
        false,
        stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $err
            ]
        ])
    );

    exit(1);
}

function lambdaExecFail(string $requestId, \Throwable $e = null, bool $exit = true)
{
    file_get_contents(
        "http://" . LAMBDA_API_BASE . "/2018-06-01/runtime/invocation/{$requestId}/error",
        false,
        stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode([
                    'errorMessage' => $e ? $e->getMessage() : 'Generic failure',
                    'errorType' => $e ? get_class($e) : 'Internal',
                    'trace' => $e ? $e->getTraceAsString() : null
                ])
            ]
        ])
    );

    if ($exit) {
        exit(1);
    }
}

function lambdaGetTask()
{
    $response = file_get_contents(
        "http://" . LAMBDA_API_BASE . "/2018-06-01/runtime/invocation/next",
        false,
        stream_context_create(['http' => ['timeout' => 1800]]) // ensure we don't time out while long-polling
    );
    $headers = [];
    foreach ($http_response_header as $header) {
        if (stripos($header, ': ') !== false) {
            [$k, $v] = explode(': ', $header, 2);
            $headers[$k] = $v;
        }
    }

    putenv('_X_AMZN_TRACE_ID=' . $headers['Lambda-Runtime-Trace-Id'] ?? '');
    global $requestId;

    $return = [
        'requestId' => $requestId = $headers['Lambda-Runtime-Aws-Request-Id'],
        'headers' => $headers,
        'body' => json_decode($response, JSON_OBJECT_AS_ARRAY)
    ];

    echo "Got invocation: " . json_encode($return, JSON_PRETTY_PRINT) . "\n";

    return $return;
}

function lambdaSubmitTask(string $requestId, $response)
{
    file_get_contents(
        "http://" . LAMBDA_API_BASE . "/2018-06-01/runtime/invocation/{$requestId}/response",
        false,
        stream_context_create([
            'http' => [
                'header' => is_string($response) ? 'Content-Type: text/plain' : 'Content-Type: application/json',
                'method' => 'POST',
                'content' => is_string($response) ? $response : json_encode($response, JSON_PRETTY_PRINT)
            ]
        ])
    );
}

try {
    require getenv('LAMBDA_TASK_ROOT') . '/index.php';
} catch (\Throwable $e) {
    lambdaExecFail($requestId, $e);
}
