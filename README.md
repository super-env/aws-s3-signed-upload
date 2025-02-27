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
---

## Usage

The basic Docker command to run this tool is:

```bash
docker run -it \
  -e AWS_ACCESS_KEY_ID=<YOUR_ACCESS_KEY_ID> \
  -e AWS_SECRET_ACCESS_KEY=<YOUR_SECRET_ACCESS_KEY> \
  firalkus/aws-s3-signed-upload \
  <BUCKET_NAME> \
  <OBJECT_KEY> \
  [--region <REGION>] \
  [--hours <HOURS>]
