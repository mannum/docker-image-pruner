FROM benzine/php:cli-8.1
#FROM gosuperscript/base-images:php-8.1-cli #This image is not publically available, and thats causing issues.
LABEL maintainer="Matthew Baggett <matthew.baggett@gosuperscript.com>" \
      org.label-schema.vcs-url="https://github.com/mannum/docker-image-pruner" \
      org.opencontainers.image.source="https://github.com/mannum/docker-image-pruner"
COPY . /app
RUN composer install && \
    chmod +x /app/pruner
ENTRYPOINT ["/app/pruner"]