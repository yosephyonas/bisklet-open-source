package main

import (
	"bytes"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"time"
)

const (
	signatureHeader = "X-Gateway-Signature"
	timestampHeader = "X-Gateway-Timestamp"
	nonceHeader     = "X-Gateway-Nonce"
)

var (
	imeiPattern       = regexp.MustCompile(`^[0-9]{15}$`)
	protocolField     = regexp.MustCompile(`^[A-Za-z0-9._:+/@=-]+$`)
	errInvalidCommand = errors.New("invalid command request")
)

func (cfg Config) Validate() error {
	if cfg.TCPAddr == "" {
		return errors.New("TCP_ADDR is required")
	}
	if cfg.OM == "" || !protocolField.MatchString(cfg.OM) {
		return errors.New("OMNI_OM contains invalid protocol characters")
	}
	if cfg.MaxFrameBytes < 256 || cfg.MaxFrameBytes > 64*1024 {
		return errors.New("MAX_FRAME_BYTES must be between 256 and 65536")
	}
	if cfg.MaxConnections < 1 || cfg.MaxConnectionsIP < 1 {
		return errors.New("connection limits must be positive")
	}
	if cfg.MaxIdleTimeouts < 1 {
		return errors.New("MAX_IDLE_TIMEOUTS must be positive")
	}
	if cfg.HTTPAddr != "" {
		if err := validateSecret("COMMAND_TOKEN", cfg.CommandToken); err != nil {
			return err
		}
		if cfg.CommandMaxBodyBytes < 1024 || cfg.CommandMaxBodyBytes > 1024*1024 {
			return errors.New("COMMAND_MAX_BODY_BYTES must be between 1024 and 1048576")
		}
		if cfg.CommandClockSkew < 10*time.Second || cfg.CommandClockSkew > 10*time.Minute {
			return errors.New("COMMAND_CLOCK_SKEW_SEC must be between 10 and 600 seconds")
		}
	}
	if cfg.EventWebhookURL != "" {
		if err := validateSecret("LARAVEL_EVENT_WEBHOOK_TOKEN", cfg.EventWebhookToken); err != nil {
			return err
		}
		parsed, err := url.ParseRequestURI(cfg.EventWebhookURL)
		if err != nil || (parsed.Scheme != "http" && parsed.Scheme != "https") || parsed.Host == "" {
			return errors.New("LARAVEL_EVENT_WEBHOOK_URL must be an absolute HTTP(S) URL")
		}
		if strings.TrimSpace(cfg.EventSpoolDir) == "" {
			return errors.New("EVENT_SPOOL_DIR is required when webhook delivery is enabled")
		}
		if cfg.EventSpoolMaxFiles < 1000 {
			return errors.New("EVENT_SPOOL_MAX_FILES must be at least 1000")
		}
	}
	for _, imei := range cfg.AllowedIMEIs {
		if !validIMEI(imei) {
			return fmt.Errorf("invalid ALLOWED_IMEIS entry %q", imei)
		}
	}
	for _, rawCIDR := range cfg.AllowedDeviceCIDRs {
		if _, _, err := net.ParseCIDR(rawCIDR); err != nil {
			return fmt.Errorf("invalid ALLOWED_DEVICE_CIDRS entry %q", rawCIDR)
		}
	}
	if cfg.Environment == "production" && len(cfg.AllowedIMEIs) == 0 {
		return errors.New("ALLOWED_IMEIS must contain provisioned locks in production")
	}
	if cfg.Environment == "production" && cfg.DebugFrames {
		return errors.New("DEBUG_FRAMES cannot be enabled in production")
	}
	return nil
}

func validateSecret(name, value string) error {
	trimmed := strings.TrimSpace(value)
	if len(trimmed) < 32 {
		return fmt.Errorf("%s must contain at least 32 characters", name)
	}
	if strings.Contains(strings.ToLower(trimmed), "change-me") {
		return fmt.Errorf("%s still contains an insecure placeholder", name)
	}
	return nil
}

func validIMEI(value string) bool {
	return imeiPattern.MatchString(strings.TrimSpace(value))
}

func safeProtocolField(value string, max int) bool {
	value = strings.TrimSpace(value)
	return value != "" && len(value) <= max && protocolField.MatchString(value)
}

type ReplayCache struct {
	mu      sync.Mutex
	entries map[string]time.Time
	ttl     time.Duration
}

func NewReplayCache(ttl time.Duration) *ReplayCache {
	if ttl < time.Minute {
		ttl = 2 * time.Minute
	}
	return &ReplayCache{entries: make(map[string]time.Time), ttl: ttl}
}

func (cache *ReplayCache) Use(nonce string, now time.Time) bool {
	cache.mu.Lock()
	defer cache.mu.Unlock()
	for key, expiresAt := range cache.entries {
		if !expiresAt.After(now) {
			delete(cache.entries, key)
		}
	}
	if _, exists := cache.entries[nonce]; exists {
		return false
	}
	cache.entries[nonce] = now.Add(cache.ttl)
	return true
}

func (g *Gateway) requireCommandAuth(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if g.cfg.CommandToken == "" {
			writeJSON(w, http.StatusServiceUnavailable, map[string]string{"error": "gateway command authentication is not configured"})
			return
		}

		r.Body = http.MaxBytesReader(w, r.Body, g.cfg.CommandMaxBodyBytes)
		body, err := io.ReadAll(r.Body)
		if err != nil {
			writeJSON(w, http.StatusRequestEntityTooLarge, map[string]string{"error": "request body is too large"})
			return
		}
		r.Body = io.NopCloser(bytes.NewReader(body))

		timestampRaw := r.Header.Get(timestampHeader)
		nonce := r.Header.Get(nonceHeader)
		provided := r.Header.Get(signatureHeader)
		timestamp, err := strconv.ParseInt(timestampRaw, 10, 64)
		if err != nil || !safeProtocolField(nonce, 128) || len(provided) != sha256.Size*2 {
			writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "invalid gateway command signature"})
			return
		}
		now := time.Now().UTC()
		issuedAt := time.Unix(timestamp, 0)
		if issuedAt.Before(now.Add(-g.cfg.CommandClockSkew)) || issuedAt.After(now.Add(g.cfg.CommandClockSkew)) {
			writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "expired gateway command signature"})
			return
		}
		expected := signRequest(g.cfg.CommandToken, timestampRaw, nonce, r.Method, requestTarget(r.URL), body)
		providedBytes, decodeErr := hex.DecodeString(provided)
		expectedBytes, _ := hex.DecodeString(expected)
		if decodeErr != nil || !hmac.Equal(providedBytes, expectedBytes) {
			writeJSON(w, http.StatusUnauthorized, map[string]string{"error": "invalid gateway command signature"})
			return
		}
		if !g.replays.Use(nonce, now) {
			writeJSON(w, http.StatusConflict, map[string]string{"error": "replayed gateway command"})
			return
		}
		if err := validateCommandJSON(r.Method, r.URL.Path, body); err != nil {
			writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
			return
		}

		r.Body = io.NopCloser(bytes.NewReader(body))
		next(w, r)
	}
}

func signRequest(secret, timestamp, nonce, method, target string, body []byte) string {
	bodyHash := sha256.Sum256(body)
	canonical := strings.Join([]string{
		timestamp,
		nonce,
		strings.ToUpper(method),
		target,
		hex.EncodeToString(bodyHash[:]),
	}, "\n")
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write([]byte(canonical))
	return hex.EncodeToString(mac.Sum(nil))
}

func requestTarget(value *url.URL) string {
	target := value.EscapedPath()
	if target == "" {
		target = "/"
	}
	if value.RawQuery != "" {
		target += "?" + value.RawQuery
	}
	return target
}

func newEventID(occurredAt time.Time, event string) string {
	random := make([]byte, 12)
	if _, err := rand.Read(random); err != nil {
		return fmt.Sprintf("%d-%s", occurredAt.UnixNano(), strings.ReplaceAll(event, ".", "-"))
	}
	return fmt.Sprintf("%d-%s", occurredAt.UnixMilli(), hex.EncodeToString(random))
}

func maskIMEI(imei string) string {
	imei = strings.TrimSpace(imei)
	if len(imei) <= 6 {
		return "***"
	}
	return imei[:3] + strings.Repeat("*", len(imei)-6) + imei[len(imei)-3:]
}

func validateCommandJSON(method, path string, body []byte) error {
	if method == http.MethodGet && len(bytes.TrimSpace(body)) == 0 {
		return nil
	}
	var fields map[string]any
	if err := json.Unmarshal(body, &fields); err != nil {
		return fmt.Errorf("%w: malformed JSON", errInvalidCommand)
	}
	if raw, exists := fields["imei"]; exists {
		imei := strings.TrimSpace(fmt.Sprint(raw))
		if !validIMEI(imei) {
			return fmt.Errorf("%w: imei must contain exactly 15 digits", errInvalidCommand)
		}
	} else if strings.HasPrefix(path, "/commands/") && path != "/commands/restart-all-connections" {
		return fmt.Errorf("%w: imei is required", errInvalidCommand)
	}
	for _, key := range []string{"request_id", "command_id", "user_id", "key", "card_number"} {
		if raw, exists := fields[key]; exists {
			value := strings.TrimSpace(fmt.Sprint(raw))
			if !safeProtocolField(value, 128) {
				return fmt.Errorf("%w: %s contains invalid protocol characters", errInvalidCommand, key)
			}
		}
	}
	if raw, exists := fields["payload"]; exists {
		items, ok := raw.([]any)
		if !ok || len(items) > 32 {
			return fmt.Errorf("%w: payload must contain at most 32 fields", errInvalidCommand)
		}
		total := 0
		for _, item := range items {
			value := strings.TrimSpace(fmt.Sprint(item))
			total += len(value)
			if !safeProtocolField(value, 256) || total > 2048 {
				return fmt.Errorf("%w: payload contains invalid or oversized fields", errInvalidCommand)
			}
		}
	}
	return validateCommandRanges(path, fields)
}

func validateCommandRanges(path string, fields map[string]any) error {
	type numericLimit struct {
		key string
		min int
		max int
	}
	ranges := map[string][]numericLimit{
		"/commands/unlock":          {{key: "reset_flag", min: 0, max: 1}},
		"/commands/ring":            {{key: "ring_times", min: 1, max: 10}},
		"/commands/track":           {{key: "interval_seconds", min: 10, max: 86400}},
		"/commands/external-device": {{key: "operation", min: 0, max: 255}},
		"/commands/ble-key":         {{key: "mode", min: 0, max: 2}},
		"/commands/wifi-positioning": {
			{key: "mode", min: 0, max: 2},
			{key: "scan_seconds", min: 1, max: 120},
		},
		"/commands/beacon-validation": {{key: "result", min: 0, max: 1}},
		"/commands/rfid-admin-card":   {{key: "operation", min: 0, max: 2}},
	}
	limits, exists := ranges[path]
	if !exists {
		return nil
	}
	for _, limit := range limits {
		raw, present := fields[limit.key]
		if !present && limit.min == 0 {
			continue
		}
		value, err := strconv.Atoi(strings.TrimSpace(fmt.Sprint(raw)))
		if err != nil || value < limit.min || value > limit.max {
			return fmt.Errorf("%w: %s must be between %d and %d", errInvalidCommand, limit.key, limit.min, limit.max)
		}
	}
	return nil
}
