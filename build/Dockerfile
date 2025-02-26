FROM composer:2 AS builder

# Set working directory in the builder stage
WORKDIR /app

# Copy only composer files first to leverage Docker's cache
COPY composer.json composer.lock ./

# Install dependencies without dev dependencies to reduce size
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist --classmap-authoritative

# Copy the rest of the application files
COPY . .

# Run post-install scripts if needed
RUN composer dump-autoload --optimize

# Final stage using php:8.4-cli-alpine
FROM php:8.4-cli-alpine

# Install required PHP extensions
RUN docker-php-ext-install pcntl

# Set working directory in the final image
WORKDIR /srv/app

# Copy the installed dependencies from the builder stage
COPY --from=builder /app /srv/app

# Entrypoint and default command
ENTRYPOINT ["php", "/srv/app/artisan", "aws:s3-signed-upload-url"]
CMD ["--help"]
