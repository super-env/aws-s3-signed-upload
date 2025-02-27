<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Closure;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

final class AwsS3SignedUploadUrl extends Command
{
    const array PROHIBITED_PREFIXES = [
        'xn--',
        'sthree-',
        'amzn-s3-demo-',
    ];
    const array PROHIBITED_SUFFIXES = [
        '-s3alias',
        '--ol-s3',
        '.mrap',
        '--x-s3',
    ];

    protected $signature = 'aws:s3-signed-upload-url {bucket_name : The S3 bucket name} {file? : The S3 file key to create the presigned URL for} {--r|region= : The AWS Region to use (default: us-east-1)} {--k|key= : The AWS Access Key ID to use} {--s|secret= : The AWS Secret Access Key to use} {--hours=24 : The number of hours the presigned URL should be valid for}';

    protected $description = 'Command description';

    private S3Client $awsClient;

    public function __construct()
    {
        parent::__construct();
    }

    private function buildAwsClient(): void
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

    public function handle(): int
    {
        $this->output->title('Generate an AWS S3 presigned upload URL');
        $this->output->info("Current Datetime: " . Carbon::now()->toDateTimeString());

        try {
            $this->buildAwsClient();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $bucketName = $this->getBucketName();
        $file = $this->getFile();
        $expiresAfter = $this->getExpiresAfter();

        try {
            $command = $this->awsClient->getCommand('PutObject', [
                'Bucket' => $bucketName,
                'Key' => $file,
            ]);
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $request = $this->awsClient->createPresignedRequest($command, '+20 minutes');

        $presignedUrl = (string)$request->getUri();

        $this->info("Generated presigned URL:\n$presignedUrl");

        return self::SUCCESS;
    }

    private function getFile(): string
    {
        $file = $this->argument('file') ?? $this->ask('Enter the S3 file to create presigned upload URL for');

        if (!empty($file)) {
            return $file;
        }

        return 'todo';
    }

    private function getBucketName(): string
    {
        $bucketName = $this->argument('bucket_name');

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

                        // Check for prohibited prefixes and suffixes
                        if (Str::of($value)->startsWith(self::PROHIBITED_PREFIXES) || Str::of($value)->endsWith(self::PROHIBITED_SUFFIXES)) {
                            $fail(
                                sprintf('Bucket name cannot start with %s or end with %s.',
                                    Str::replaceLast(', ', ', or ', implode(', ', self::PROHIBITED_PREFIXES)),
                                    Str::replaceLast(', ', ', or ', implode(', ', self::PROHIBITED_SUFFIXES))
                                )
                            );
                        }

                        // Check for adjacent periods
                        if (Str::of($value)->contains('..')) {
                            $fail('Bucket name cannot contain adjacent periods.');
                        }

                        // Check for dashes next to periods
                        if (Str::of($value)->contains(['-.', '.-'])) {
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

            throw new RuntimeException("Invalid S3 bucket name: $bucketName.");
        }

        return $bucketName;
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
    private function getAwsRegion(): string
    {
        $endpointsFile = base_path('/vendor/aws/aws-sdk-php/src/data/endpoints.json.php');
        $endpointsData = require($endpointsFile);
        $region = $this->option('region') ?? env('AWS_REGION', 'us-east-1');

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

    private function getExpiresAfter(): Carbon
    {
        $hours = $this->option('hours') ?? $this->ask('Enter the number of hours for the URL to expire after (max 168 hours (7 days))');

        if (!is_numeric($hours) || $hours <= 0 || $hours > 168) {
            throw new RuntimeException('Invalid hours value. Please specify a number between 1 and 168.');
        }

        $this->info(sprintf("URL will expire after %s hours.", $hours));

        return Carbon::now()->addHours($hours);
    }
}
