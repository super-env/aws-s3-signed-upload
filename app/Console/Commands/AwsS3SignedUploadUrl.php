<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

final class AwsS3SignedUploadUrl extends Command
{

    protected $signature = 'aws:s3-signed-upload-url {bucket_name? : The S3 bucket name} {--r|region= : The AWS Region to use (optional)} {--k|key= : The AWS Access Key ID to use} {--s|secret= : The AWS Secret Access Key to use}';

    protected $description = 'Command description';

    private S3Client $awsClient;

    public function __construct()
    {
        parent::__construct();
    }

    private function buildAwsClient()
    {
        $this->awsClient = new S3Client([
            'version' => 'latest',
            'region' => $this->getAwsRegion(),
            'credentials' => [
                'key' => $this->getAwsAccessKeyId(),
                'secret' => $this->getAwsSecretKey(),
            ],
        ]);
    }

    public function handle(): void
    {
        $this->buildAwsClient();

        $bucketName = $this->getBucketName();

        exit(0);
    }

    private function getBucketName(): string
    {
        $bucketName = $this->argument('bucket_name') ?? $this->ask('Enter the S3 bucket name to create presigned upload URL for');

        $validator = Validator::make(
            ['bucket_name' => $bucketName],
            [
                'bucket_name' => [
                    'required',
                    'string',
                    'min:3',
                    'max:63',
                    'regex:/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/',
                    function (string $attribute, string $value, Closure $fail) {
                        // Check if formatted as IP address
                        if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $value)) {
                            $fail('Bucket name cannot be formatted as an IP address.');
                        }

                        // Check for prohibited prefixes
                        if (str_starts_with($value, 'xn--') || str_starts_with($value, 'sthree-')) {
                            $fail('Bucket name cannot start with "xn--" or "sthree-".');
                        }

                        // Check for prohibited suffix
                        if (str_ends_with($value, '-s3alias')) {
                            $fail('Bucket name cannot end with "-s3alias".');
                        }

                        // Check for adjacent periods
                        if (str_contains($value, '..')) {
                            $fail('Bucket name cannot contain adjacent periods.');
                        }

                        // Check for dashes next to periods
                        if (str_contains($value, '-.') || str_contains($value, '.-')) {
                            $fail('Bucket name cannot contain dashes next to periods.');
                        }

                        // Check if only contains valid characters
                        if (!preg_match('/^[a-z0-9.-]+$/', $value)) {
                            $fail('Bucket name can only contain lowercase letters, numbers, dots, and hyphens.');
                        }
                    }
                ]
            ]
        );

        if ($validator->fails()) {
            $this->error('Invalid S3 bucket name:');

            foreach ($validator->errors()->all() as $error) {
                $this->line("  - $error");
            }

            exit(1);
        }

        return $bucketName;
    }

    private function getAwsRegion(): string
    {
        $endpointsFile = base_path('/vendor/aws/aws-sdk-php/src/data/endpoints.json.php');
        $endpointsData = require($endpointsFile);
        $region = $this->option('region') ?? env('AWS_REGION');

        if (!$region) {
            throw new RuntimeException('AWS Region must be specified either through the --region option or the AWS_REGION environment variable.');
        }

        foreach ($endpointsData['partitions'] as $partitionData) {
            if (!isset($partitionData['regions'][$region])) {
                throw new RuntimeException(sprintf('Invalid AWS Region: "%s". Please specify a valid region.', $region));
            }

            $this->info(sprintf("Using AWS Region: %s - %s", $region, $partitionData['regions'][$region]['description']));

            break;
        }

        return $region;
    }


    /**
     * Retrieve the AWS Region.
     *
     * This method retrieves the AWS Region from either the --region
     * command-line option or the AWS_REGION environment variable. It validates
     * the region against the available AWS regions data, and throws a runtime
     * exception if the region is invalid or unspecified.
     *
     * @throws RuntimeException If the AWS Region is missing or invalid.
     */
    private function getAwsAccessKeyId(): string
    {
        $key = $this->option('key') ?? env('AWS_ACCESS_KEY_ID');

        if (!$key) {
            throw new RuntimeException('AWS Access Key ID must be specified either through the --key option or the AWS_ACCESS_KEY_ID environment variable.');
        }

        if (!preg_match('/^(AKIA|ASIA)[0-9A-Z]{16}$/', $key)) {
            throw new RuntimeException('Invalid AWS Access Key ID format. It must start with "AKIA" or "ASIA" followed by 16 alphanumeric characters.');
        }

        $this->info(sprintf("Using AWS Access Key ID: %s", Str::of($key)->mask('*', 4, 12)));

        return $key;
    }

    /**
     * Retrieve the AWS Secret Access Key.
     *
     * This method retrieves the AWS Secret Access Key from either the
     * --secret command-line option or the AWS_SECRET_ACCESS_KEY
     * environment variable. It validates the format of the key to ensure
     * it adheres to AWS requirements, and throws a runtime exception
     * if the key is missing or invalid.
     *
     * @throws RuntimeException If the Secret Access Key is missing or invalid.
     */
    private function getAwsSecretKey(): string
    {
        $secret = $this->option('secret') ?? env('AWS_SECRET_ACCESS_KEY');

        if (!$secret) {
            throw new RuntimeException('AWS Secret Access Key must be specified either through the --secret option or the AWS_SECRET_ACCESS_KEY environment variable.');
        }

        if (!preg_match('/^[A-Za-z0-9\/+=]{40}$/', $secret)) {
            throw new RuntimeException('Invalid AWS Secret Access Key format. It must be 40 characters long and contain only alphanumeric characters, forward slashes, plus signs, or equals signs.');
        }

        $this->info(sprintf("Using AWS Secret Access Key: %s", Str::of($secret)->mask('*', 4, 32)));

        return $secret;
    }
}
