FROM alpine:3.24@sha256:f59abe656b692d901e184be3445e084786602108599d2a51d229c0f5a48c71bf

RUN apk add --no-cache curl jq restic \
    && mkdir /source /repository \
    && chown nobody:nobody /source /repository

COPY --chmod=755 run-every backup flaky-sync /usr/local/bin/

USER nobody
WORKDIR /tmp
