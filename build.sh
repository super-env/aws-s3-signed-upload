#!/bin/bash

# Build and tag the Docker image
docker build -t aws-s3-signed-upload -f build/Dockerfile .
