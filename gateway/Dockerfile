FROM golang:1.25.12-alpine AS build

WORKDIR /src
COPY go.mod ./
COPY *.go ./
RUN go test ./... && CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/omni-tcp-gateway .

FROM alpine:3.22

RUN apk add --no-cache ca-certificates \
    && adduser -D -H -u 10001 gateway \
    && mkdir -p /var/lib/omni-gateway/events \
    && chown -R gateway:gateway /var/lib/omni-gateway
USER gateway

COPY --from=build /out/omni-tcp-gateway /usr/local/bin/omni-tcp-gateway

EXPOSE 9000 9001

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD wget -q -O - http://127.0.0.1:9001/healthz >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/omni-tcp-gateway"]
