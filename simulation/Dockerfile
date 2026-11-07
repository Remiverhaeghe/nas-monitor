FROM alpine:3.22@sha256:bfe7ceb9f09ec0566c48fb93356e45a47a00be5a1dd14fa5b909e7709c66b034

RUN apk add --no-cache curl jq restic \
    && mkdir /source /repository \
    && chown nobody:nobody /source /repository

COPY --chmod=755 run-every backup flaky-sync /usr/local/bin/

USER nobody
WORKDIR /tmp
