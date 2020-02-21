<?php

use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use ZBateson\MailMimeParser\MailMimeParser;

require __DIR__ . '/vendor/autoload.php';

$s3Client = new S3Client(['region' => 'us-east-1', 'version' => 'latest']);
$sesClient = new SesClient(['region'  => 'us-east-1', 'version' => '2010-12-01']);
$parser = new MailMimeParser();

function recapitalize(string $string)
{
    return implode('', array_map(function(string $char) {
        return random_int(0, 1) ? mb_strtoupper($char) : mb_strtolower($char);
    }, mb_str_split($string)));
}

function dispatchS3Upload($task, S3Client $s3Client, SesClient $sesClient, MailMimeParser $parser)
{
    $rawEmail = $s3Client->getObject([
        'Bucket' => $task['body']['Records'][0]['s3']['bucket']['name'],
        'Key' => $task['body']['Records'][0]['s3']['object']['key']
    ])['Body'];

    $email = $parser->parse($rawEmail);

    $sesClient->sendEmail([
        'Destination' => [
            'ToAddresses' => [$from = $email->getHeaderValue('from')]
        ],
        'Source' => $email->getHeader('to')->getAddresses()[0],
        'Message' => [
            'Subject' => [
                'Charset' => 'UTF-8',
                'Data' => 'RE: ' . ($subject = $email->getHeaderValue('subject'))
            ],
            'Body' => [
                'Text' => [
                    'Charset' => 'UTF-8',
                    'Data' => recapitalize($email->getTextContent())
                ]
            ]
        ]
    ]);

    lambdaSubmitTask($task['requestId'], 'Handled email from ' . $from . ' with subject ' . $subject);
}

function dispatchAPICall($task)
{
    lambdaSubmitTask($task['requestId'], [
        'statusCode' => isset($task['body']['queryStringParameters']['message']) ? 200 : 400,
        'body' => json_encode([
            'message' => recapitalize(
                $task['body']['queryStringParameters']['message'] ??
                'Please provide a message as "message" in your query string'
            )
        ])
    ]);
}

while (true) {
    $task = lambdaGetTask();

    if (isset($task['body']['version'])) {
        dispatchAPICall($task);
    } else {
        dispatchS3Upload($task, $s3Client, $sesClient, $parser);
    }
}
