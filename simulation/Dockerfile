FROM alpine:3.22

RUN apk add --no-cache curl jq restic \
    && mkdir /source /repository \
    && chown nobody:nobody /source /repository

COPY --chmod=755 run-every backup flaky-sync /usr/local/bin/

USER nobody
WORKDIR /tmp
