SpongeBot
=========

An example of a PHP-based Lambda function using Bref and Serverless Framework.

## Setup

1. Make sure you have the AWS CLI installed and configured with your AWS keys.
2. `composer install` 
3. `npm i -g serverless` (you'll need Node for this)
4. `serverless deploy`

This will set up two API endpoints: one directly interacting with Lambda's event
structure, one using more common PHP conventions via FPM. The former is available
at `/`, the latter at `/fpm`.

The S3 handler example, while theoretically build-able with Serverless + Bref, is
not set up on this branch.
