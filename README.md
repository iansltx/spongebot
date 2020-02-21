SpongeBot
=========

An example of a PHP-based Lambda function, from scratch...ish (runtime is based on Bref).

## Basic Setup

1. `composer install`
2. Zip the contents of `runtime` and upload as a Lambda Layer; specify that it's compatible
with custom runtimes. What you do next depends on how you're deploying.
3. Create a Lambda function with enough permissions to send SES emails, read from S3, and do
normal Lambda activities. An IAM role including policies of AmazonS3ReadOnlyAccess,
AmazonSESFullAccess, and AWSLambdaBasicExecutionRole is overkill but will get the job done.
4. Zip the contents outside `runtime` and upload to the function.

## Email handler

1. Set up SES, including domain verification + MX records. This may take a bit to verify, and
you'll need to open a support ticket to actually be able to send email to anything other than
hand-verified email addresses.
2. Add an SES rule to push inbound messages from your desired email address to an S3 bucket.
It's easier to get this set up if you create the bucket within the rule creation process.
3. Add S3 "all object create events" to the above bucket as a trigger for the Lambda function.
4. Send your bot an email!

## API

1. Under AWS's API Gateway UI, click Build on the HTTP API card; you'll have to click Create API
to see that card if you already have API gateways set up.
2. Click Add Integration, select an Integration type of Lambda, then select your function as
the integration target. Specify an arbitrary name for the API, then click Next.
3. Make note of, and change if you like, the Resource path on the routes screen, then click Next.
4. Click Next, then Create; the default deployment stage is good enough for what we want to do.
5. Hit the Invoke URL, plus the route you set in step 3, in a web browser. Optionally, provide
the "message" value in the query string.
