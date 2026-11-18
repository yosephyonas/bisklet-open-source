# OMNI TCP Gateway

Security-hardened TCP gateway for OMNI bicycle locks and the Bisklet Laravel backend.

## Production requirements

The process fails to start in production unless:

- `COMMAND_TOKEN` and `LARAVEL_EVENT_WEBHOOK_TOKEN` are independent secrets with at least 32 characters.
- `ALLOWED_IMEIS` contains every provisioned 15-digit lock IMEI.
- `EVENT_SPOOL_DIR` is writable and backed by persistent storage.
- `DEBUG_FRAMES` is disabled.

Keep the HTTP command listener private. Only TCP port `9000` should be reachable by lock devices; port `9001` should remain on the private application network.

The legacy OMNI TCP protocol does not cryptographically authenticate devices. The gateway therefore permanently binds a connection to its first allowlisted IMEI and optionally restricts source networks with `ALLOWED_DEVICE_CIDRS`. Production deployments should additionally use a private carrier APN/VPN or firewall allowlist whenever possible.

See [.env.example](.env.example) for all limits and configuration.

## Verification

```bash
go test ./...
go test -race ./...
go vet ./...
govulncheck ./...
```

The parser and command validation include native Go fuzz targets:

```bash
go test -fuzz=FuzzParseCMDR -fuzztime=30s
go test -fuzz=FuzzSignedCommandJSON -fuzztime=30s
```
