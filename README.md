# aws-s3-signed-uploads

A simple Dockerized CLI for generating Amazon S3 presigned upload URLs. With this tool, you can create URLs that allow file uploads directly to S3 without sharing your AWS credentials. You can generate URLs valid for up to 7 days (168 hours).

---

## Table of Contents

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Usage](#usage)
    - [1. Run with environment variables](#1-run-with-environment-variables)
    - [2. Run without environment variables set](#2-run-without-environment-variables-set)
    - [Optional Parameters](#optional-parameters)
    - [Examples](#examples)
- [Notes](#notes)
- [License](#license)

---

## Features

- **Generate presigned URLs** for uploading files to S3 without exposing your AWS credentials.
- **Max URL lifetime**: Up to 7 days (168 hours).
- **Interactive**: If AWS credentials are not passed as environment variables, you will be prompted to enter them securely.
- **Configurable**: You can set the AWS region and the desired validity period (in hours).

---

## Prerequisites

1. **Docker** must be installed on your machine.  
   Visit [Docker’s official documentation](https://docs.docker.com/get-docker/) for installation instructions.

2. **AWS credentials**:
    - `AWS_ACCESS_KEY_ID`
    - `AWS_SECRET_ACCESS_KEY`

   Either set them as environment variables **OR** be ready to enter them interactively when prompted.

### AWS Credentials Policy Requirements

The AWS credentials used by this tool must have permission to perform **`PutObject`** on the target S3 bucket and object key. For example, your IAM policy could look like this:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "s3:PutObject",
      "Resource": "arn:aws:s3:::<BUCKET_NAME>/*"
    }
  ]
}
```

Replace <BUCKET_NAME> with the name of your S3 bucket. Make sure to also customize the path if needed to restrict object keys to a specific prefix.

---

## Usage

The basic Docker command to run this tool is:

```bash
docker run -it \
  -e AWS_ACCESS_KEY_ID=<YOUR_ACCESS_KEY_ID> \
  -e AWS_SECRET_ACCESS_KEY=<YOUR_SECRET_ACCESS_KEY> \
  superenv/aws-s3-signed-upload \
  <BUCKET_NAME> \
  <OBJECT_KEY> \
  [--region <REGION>] \
  [--hours <HOURS>]
```

1. Run with environment variables
   If you already have your AWS credentials set in your environment, you can pass them into the Docker container:

```bash
docker run -it \
  -e AWS_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID \
  -e AWS_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY \
  superenv/aws-s3-signed-upload \
  my-bucket \
  file-key

```

This will generate a presigned URL for uploading a file to the my-bucket S3 bucket in the default region (us-east-1) valid for 24 hours.

2. Run without environment variables set

If you do not have the AWS credentials in your environment variables, simply run:

```bash
docker run -it superenv/aws-s3-signed-upload my-bucket file-key
```

The CLI will prompt you to enter:
- **AWS Access Key ID**
- **AWS Secret Access Key**

Enter them when prompted. Then, it will generate the presigned URL as before

---

### Optional Parameters
- `--region <REGION>`
Defaults to `us-east-1`. Use this if you bucket is located in a different AWS region.
- `--hours <HOURS>`
Defaults to `24`. This sets how long the resigned URL will be valid, in hours.
**NOTE:** The maximum allowed value is 168 (7 days)

Examples
Use the default region and default 24 hours:

```bash
docker run -it \
-e AWS_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID \
-e AWS_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY \
superenv/aws-s3-signed-upload \
my-bucket \
test.txt
```
**Output:** A URL that is valid for 24 hours.

Specify a region and a custom duration:

```bash
docker run -it \
-e AWS_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID \
-e AWS_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY \
superenv/aws-s3-signed-upload \
my-bucket \
test.txt \
--region us-west-2 \
--hours 48
```

**Output:** A URL valid for 48 hours for a bucket in the us-west-2 region.

---

# Notes
1. **Security**: Presigned URLs give public access to upload to that specific object key while valid. Use them carefully and only share them with trusted parties.
2. **Bucket Policy**: Ensure that your S3 bucket permissions allow presigned URLs to be used to upload objects.
3. **Maximum Hours**: If you specify more than 168 hours, the tool will automatically reject it.

---

# License

This project is available under the MIT License. Feel free to use, modify, and distribute it as you see fit.
