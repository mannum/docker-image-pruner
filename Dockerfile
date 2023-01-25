FROM gosuperscript/base-images:php-8.1-cli
LABEL maintainer="Matthew Baggett <matthew.baggett@gosuperscript.com>" \
      org.label-schema.vcs-url="https://github.com/mannum/docker-image-pruner" \
      org.opencontainers.image.source="https://github.com/mannum/docker-image-pruner"
COPY . /app
RUN composer install && \
    chmod +x /app/pruner
ENTRYPOINT ["/app/pruner"]