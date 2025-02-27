#!/bin/bash

IMAGE_TAG=${1:-superenv/aws-s3-signed-upload}

echo "Using image tag: $IMAGE_TAG"

# Build and tag the Docker image
docker build -t "$IMAGE_TAG" -f build/Dockerfile .
