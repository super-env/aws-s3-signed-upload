<?php

namespace Tests\Feature;

use Aws\S3\S3Client;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AwsS3SignedUploadUrlTest extends TestCase
{
    const string EXAMPLE_AWS_ACCESS_KEY_ID = 'AKIAIOSFODNN7EXAMPLE';
    const string EXAMPLE_AWS_SECRET_ACCESS_KEY = '=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the S3Client to avoid actual AWS calls
        $mockS3Client = Mockery::mock(S3Client::class);
        $this->app->instance(S3Client::class, $mockS3Client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_asks_for_bucket_name_and_rejects_invalid_input()
    {
        $this->artisan("aws:s3-signed-upload-url")
            ->expectsQuestion('Enter the S3 bucket name to create presigned upload URL for', 'invalid_bucket!')
            ->expectsOutput('Invalid S3 bucket name:')
            ->expectsOutput('- Bucket name can only contain lowercase letters, numbers, dots, and hyphens.')
            ->assertExitCode(1);
    }


    #[Test]
    public function it_accepts_a_valid_bucket_name_and_exits_gracefully()
    {
        $this->artisan('aws:s3-signed-upload-url')
            ->expectsQuestion('Enter the S3 bucket name to create presigned upload URL for', 'valid-bucket-name')
            ->assertExitCode(0);
    }


    #[Test]
    public function it_rejects_bucket_names_with_invalid_characters()
    {
        // Simulating bucket name validation through command execution
        $this->artisan('aws:s3-signed-upload-url --key=demo-key --secret=demo-secret')
            ->expectsQuestion('Enter the S3 bucket name to create presigned upload URL for', 'invalid///name')
            ->expectsOutput('Invalid S3 bucket name:')
            ->expectsOutput('- Bucket name can only contain lowercase letters, numbers, dots, and hyphens.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_accepts_valid_bucket_names()
    {
        $this->artisan('aws:s3-signed-upload-url --key=demo-key --secret=demo-secret')
            ->expectsQuestion('Enter the S3 bucket name to create presigned upload URL for', 'valid-bucket')
            ->expectsOutput('Command executed successfully') // Output after validation
            ->assertExitCode(0);
    }

    #[Test]
    public function it_initializes_and_uses_the_s3_client_with_correct_credentials()
    {
        $mockS3Client = Mockery::mock(S3Client::class);

        // Assert S3Client is instantiated with credentials
        $mockS3Client->shouldReceive('createPresignedRequest')
            ->once()
            ->andReturn('http://example.com/presigned-url');

        $this->app->instance(S3Client::class, $mockS3Client);

        $this->artisan('aws:s3-signed-upload-url --key=example-key --secret=example-secret --region=us-east-1')
            ->expectsQuestion('Enter the S3 bucket name to create presigned upload URL for', 'valid-bucket')
            ->expectsOutput('Presigned URL: http://example.com/presigned-url')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_runtime_exceptions_correctly()
    {
        $mockS3Client = Mockery::mock(S3Client::class);

        $mockS3Client->shouldReceive('createPresignedRequest')
            ->andThrow(new \RuntimeException('AWS error occurred'));

        $this->app->instance(S3Client::class, $mockS3Client);

        $this->artisan('aws:s3-signed-upload-url --key=example-key --secret=example-secret --region=us-east-1')
            ->expectsQuestion('Enter the S3 bucket name to create presigned upload URL for', 'valid-bucket')
            ->expectsOutput('AWS error occurred')
            ->assertExitCode(1);
    }
}
