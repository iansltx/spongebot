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

$dispatchS3Upload = function($event) use ($parser, $s3Client, $sesClient)
{
    $rawEmail = $s3Client->getObject([
        'Bucket' => $event['Records'][0]['s3']['bucket']['name'],
        'Key' => $event['Records'][0]['s3']['object']['key']
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

    return 'Handled email from ' . $from . ' with subject ' . $subject;
};

$dispatchAPICall = fn ($event) => [
    'statusCode' => isset($event['queryStringParameters']['message']) ? 200 : 400,
    'body' => json_encode([
        'message' => recapitalize(
            $event['queryStringParameters']['message'] ??
            'Please provide a message as "message" in your query string'
        )
    ])
];

return function($event) use ($dispatchAPICall, $dispatchS3Upload) {
    return call_user_func(isset($event['version']) ? $dispatchAPICall : $dispatchS3Upload, $event);
};
