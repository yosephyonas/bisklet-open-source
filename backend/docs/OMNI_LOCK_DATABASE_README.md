# Omni Horseshoe Bike Lock - Laravel Database README

Scope: database only (tables + fields + indexes + ownership).
Rule: Laravel is the system of record (source of truth). Go only streams events
and executes commands; Laravel stores everything durable.

## 0) Database choice

Use MySQL 8 / MariaDB (InnoDB) with utf8mb4. Laravel migrations are the right
place to define indexes and constraints.

## 1) Key identifiers and data types

### 1.1 IMEI

Store IMEI as CHAR(15) (or VARCHAR(15)), not numeric.

- IMEI is an identifier, not a number you calculate with.
- Storing it as string avoids formatting issues and is index-friendly.

### 1.2 Primary keys

Use UUID/ULID for externally referenced entities (rides, commands, alarms) to
avoid guessable IDs. Laravel supports UUID/ULID patterns commonly.

### 1.3 Time fields you should store everywhere

For every telemetry/command:

- device_ts (string YYMMDDhhmmss from lock, as received)
- received_at (server time DATETIME)
- Optional: op_unix (BIGINT) when protocol includes Unix time

Use BIGINT when you truly need big ranges like Unix seconds.

## 2) Core tables (minimum production set)

### 2.1 devices (one row per lock)

Purpose: fleet registry + current state snapshot.

Fields

- imei CHAR(15) PK
- model (nullable string)
- firmware_version (nullable string)
- hardware_version (nullable string)
- is_active (bool)
- Connectivity
  - last_seen_at (datetime)
  - last_ip (nullable string) (from Go gateway, optional)
- Current state snapshot (latest known)
  - lock_status (tinyint: 0 unlocked / 1 locked / null unknown)
  - battery_voltage (smallint: raw like 389, 412)
  - battery_percent (tinyint nullable)
  - signal_strength (tinyint nullable)
  - gps_sat (tinyint nullable)
  - last_lat (decimal nullable)
  - last_lng (decimal nullable)
  - last_gps_at (datetime nullable)
- created_at, updated_at

Indexes

- last_seen_at
- (is_active, last_seen_at) for fleet health views

### 2.2 telemetry_events (append-only log of everything from lock)

Purpose: single raw truth stream for audits and debugging.

Fields

- id (uuid/ulid) PK
- imei FK -> devices.imei
- event_type (enum/string)
  Examples: Q0, H0, D0, D2, W0, S5, K0, L1, U0, U1, etc.
- device_ts (string YYMMDDhhmmss)
- received_at (datetime)
- raw_frame (text) (exact *CMDR...# line)
- parsed_json (JSON) (normalized fields for queries)
- Optional: op_unix (bigint) (if present in payload)

Indexes (important)

- (imei, received_at) for latest telemetry per device and time queries
- (event_type, received_at)
- Consider partitioning by time later if volume is huge (optional)

### 2.3 gps_points (optional but recommended if you want fast maps)

Purpose: map queries should not scan all telemetry JSON.

Fields

- id (uuid/ulid) PK
- imei FK
- device_ts (string)
- received_at (datetime)
- lat (decimal)
- lng (decimal)
- is_valid (bool) (A vs VA)
- sat (tinyint nullable)
- hdop (decimal/float nullable)
- alt_m (decimal nullable)
- source (enum: D0)
  D1 configures repeated tracking, but the resulting GPS uploads still arrive as D0 payloads.
- raw_ref_event_id (FK -> telemetry_events.id, nullable)

Indexes

- (imei, received_at)
- (received_at) for cleanup/retention jobs

### 2.4 lock_commands (everything Laravel asked Go to do)

Purpose: command tracking + idempotency + auditing.

Fields

- id (uuid/ulid) PK
- request_id (string) unique (idempotency key from mobile request)
- imei FK
- user_id FK to users
- command (enum/string): unlock, lock, ring, gps_once, status_query,
  ble_key_get, ble_key_set, ota_start, ota_chunk, etc.
- status (enum/string): pending, accepted, sent, success, failed, timeout, offline
- requested_at (datetime)
- sent_at (datetime nullable)
- completed_at (datetime nullable)
- error_code (nullable string/int)
- result_json (JSON nullable) (unlock result, lock result, etc.)
- Protocol correlation
  - reset_flag (tinyint nullable)
  - cycle_min (tinyint nullable)
  - op_unix (bigint nullable)

Indexes

- (imei, requested_at)
- (user_id, requested_at)
- status

### 2.5 rides (business truth of rentals)

Purpose: billing + ride lifecycle. This is where money logic anchors.

Fields

- id (uuid/ulid) PK
- user_id FK
- imei FK
- status (enum): active, ended, disputed, cancelled
- started_at (datetime)
- ended_at (datetime nullable)
- start_command_id (FK -> lock_commands.id)
- end_event_id (FK -> telemetry_events.id nullable) (e.g., L1 report)
- start_lat, start_lng (decimal nullable)
- end_lat, end_lng (decimal nullable)
- Billing
  - minutes (int nullable)
  - price_total (decimal)
  - currency (string like ETB)
  - billing_status (enum): unbilled, billed, failed, refunded
- created_at, updated_at

Indexes

- (imei, status) (find active ride for a device)
- (user_id, started_at)
- (status, started_at)

### 2.6 alarms (from W0, etc.)

Purpose: theft/fall alerts + operations workflow.

Fields

- id (uuid/ulid) PK
- imei FK
- type (tinyint or enum): 1 illegal movement, 2 fall, 6 cleared
- device_ts (string)
- received_at (datetime)
- is_active (bool) (cleared or not)
- handled_at (datetime nullable)
- telemetry_event_id (FK -> telemetry_events.id)
- notes (nullable text)

Indexes

- (imei, received_at)
- (type, received_at)
- (is_active, received_at)

### 2.7 ble_keys (if you will manage BLE keys from server K0)

Purpose: store current BLE key per device, rotate safely.

Fields

- id (uuid/ulid) PK
- imei FK
- key_value (string length 8 or binary) (store encrypted-at-rest if possible)
- version (int, optional)
- rotated_at (datetime)
- created_at

Indexes

- (imei, rotated_at desc)

### 2.8 firmware_updates (OTA orchestration in Laravel)

Purpose: Laravel schedules; Go executes chunks.

Fields

- id (uuid/ulid) PK
- imei FK
- target_version (string)
- status (enum): scheduled, running, paused, failed, completed
- scheduled_at (datetime)
- started_at (datetime nullable)
- completed_at (datetime nullable)
- progress_percent (tinyint)
- error_message (nullable text)

Indexes

- (status, scheduled_at)
- (imei, status)

## 3) Required constraints (to avoid corruption)

- devices.imei is the only authoritative device identifier.
- Only one active ride per IMEI at a time (enforce via app logic + DB
  constraint if you choose).
- lock_commands.request_id must be unique (idempotency).
- telemetry_events is append-only; never update raw frames.

## 4) Performance + retention (do this early)

Telemetry becomes time-series-ish quickly:

- Use compound index (imei, received_at) on telemetry and GPS tables for fast
  latest + range per device queries.
- Add a retention policy:
  - Keep telemetry_events raw for e.g. 30-90 days
  - Keep gps_points for longer if needed
- If you scale big (1k-10k locks), consider MySQL partitioning by date for
  telemetry-heavy tables.

## 5) What Go sends vs what Laravel stores (strict)

### Go -> Laravel event types you should persist

- device.online (from Q0)
- telemetry.heartbeat (H0)
- telemetry.gps (D0; D1 only configures repeated D0 uploads)
- command.track.result (D1 tracking interval echo/ack)
- telemetry.wifi_positioning (D2 WiFi AP scan data; no lat/lng)
- alarm.triggered/cleared (W0)
- command.unlock.result (L0 result)
- ride.ended (L1 report)
- command.external_lock.result (L5 external lock control/status)
- device.cable_lock_info (G1 cable lock firmware report)
- beacon.scan.result (B0 Beacon return-zone scan result)
- rfid.unlock.request (C0 RFID card unlock request)
- rfid.admin_card.result (C1 administrator RFID card state)
- ota.status (U0/U1/U2 firmware lifecycle reports)

Laravel stores:

- raw (raw_frame) + normalized (parsed_json) + aggregates (snapshot fields in
  devices)
