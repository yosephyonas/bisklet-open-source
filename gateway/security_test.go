package main

import (
	"bytes"
	"errors"
	"fmt"
	"net"
	"net/http"
	"net/http/httptest"
	"strconv"
	"strings"
	"testing"
	"time"
)

func TestDeviceConnectionRejectsIMEIChanges(t *testing.T) {
	g, err := NewGateway(Config{
		OM:             "OM",
		Environment:    "development",
		EventQueueSize: 1,
	})
	if err != nil {
		t.Fatal(err)
	}
	server, client := net.Pipe()
	defer server.Close()
	defer client.Close()
	connection := NewDeviceConn(server)

	if err := g.bindDeviceMessage(connection, Message{Om: "OM", IMEI: "867250300000001"}); err != nil {
		t.Fatalf("expected first identity to bind: %v", err)
	}
	if err := g.bindDeviceMessage(connection, Message{Om: "OM", IMEI: "867250300000002"}); !errors.Is(err, errDeviceIMEIMismatch) {
		t.Fatalf("expected IMEI mismatch, got %v", err)
	}
}

func TestRemoteIPHandlesIPv4AndIPv6(t *testing.T) {
	if got := remoteIP("203.0.113.4:9000"); got != "203.0.113.4" {
		t.Fatalf("unexpected IPv4 address: %q", got)
	}
	if got := remoteIP("[2001:db8::1]:9000"); got != "2001:db8::1" {
		t.Fatalf("unexpected IPv6 address: %q", got)
	}
}

func TestConnectionLimiterEnforcesGlobalAndPerIPLimits(t *testing.T) {
	limiter := NewConnectionLimiter(2, 1)
	if !limiter.Acquire("203.0.113.1") {
		t.Fatal("expected first connection to pass")
	}
	if limiter.Acquire("203.0.113.1") {
		t.Fatal("expected per-IP limit to reject second connection")
	}
	if !limiter.Acquire("203.0.113.2") {
		t.Fatal("expected second IP to use remaining global capacity")
	}
	if limiter.Acquire("203.0.113.3") {
		t.Fatal("expected global limit to reject connection")
	}
	limiter.Release("203.0.113.1")
	if !limiter.Acquire("203.0.113.3") {
		t.Fatal("expected released capacity to be reusable")
	}
}

func TestSignedCommandRejectsReplay(t *testing.T) {
	secret := strings.Repeat("x", 32) // Synthetic test-only key
	g, err := NewGateway(Config{
		CommandToken:        secret,
		CommandMaxBodyBytes: 32768,
		CommandClockSkew:    time.Minute,
		EventQueueSize:      1,
	})
	if err != nil {
		t.Fatal(err)
	}
	handler := g.requireCommandAuth(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	})
	body := []byte(`{"imei":"867250300000001","user_id":"42"}`)
	timestamp := strconv.FormatInt(time.Now().UTC().Unix(), 10)
	nonce := "unique-command-nonce"
	signature := signRequest(secret, timestamp, nonce, http.MethodPost, "/commands/unlock", body)

	request := func() *http.Request {
		req := httptest.NewRequest(http.MethodPost, "/commands/unlock", bytes.NewReader(body))
		req.Header.Set(timestampHeader, timestamp)
		req.Header.Set(nonceHeader, nonce)
		req.Header.Set(signatureHeader, signature)
		return req
	}

	first := httptest.NewRecorder()
	handler(first, request())
	if first.Code != http.StatusNoContent {
		t.Fatalf("expected first request to pass, got %d: %s", first.Code, first.Body.String())
	}
	second := httptest.NewRecorder()
	handler(second, request())
	if second.Code != http.StatusConflict {
		t.Fatalf("expected replay conflict, got %d", second.Code)
	}
}

func TestRequestSignatureMatchesCrossLanguageVector(t *testing.T) {
	body := []byte(`{"imei":"867250300000001","request_id":"request-1"}`)
	got := signRequest(
		"0123456789abcdef0123456789abcdef",
		"1784131200",
		"vector-001",
		http.MethodPost,
		"/commands/reboot",
		body,
	)
	want := "c4e71564c22318f128758f34351e4d762ac7d60edca5befeef982487e85dc334"
	if got != want {
		t.Fatalf("signature mismatch: got %s want %s", got, want)
	}
}

func TestCommandBodySizeIsLimited(t *testing.T) {
	g, err := NewGateway(Config{
		CommandToken:        "0123456789abcdef0123456789abcdef",
		CommandMaxBodyBytes: 16,
		CommandClockSkew:    time.Minute,
		EventQueueSize:      1,
	})
	if err != nil {
		t.Fatal(err)
	}
	handler := g.requireCommandAuth(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	})
	req := httptest.NewRequest(http.MethodPost, "/commands/unlock", bytes.NewReader(make([]byte, 17)))
	response := httptest.NewRecorder()
	handler(response, req)
	if response.Code != http.StatusRequestEntityTooLarge {
		t.Fatalf("expected 413, got %d", response.Code)
	}
}

func TestProductionConfigRequiresStrongSecretsAndProvisionedIMEIs(t *testing.T) {
	config := Config{
		TCPAddr:             ":9000",
		HTTPAddr:            ":9001",
		OM:                  "OM",
		Environment:         "production",
		MaxFrameBytes:       4096,
		MaxConnections:      100,
		MaxConnectionsIP:    5,
		MaxIdleTimeouts:     3,
		CommandToken:        "0123456789abcdef0123456789abcdef",
		CommandMaxBodyBytes: 32768,
		CommandClockSkew:    time.Minute,
	}
	if err := config.Validate(); err == nil {
		t.Fatal("expected missing production IMEI allowlist to fail")
	}
	config.AllowedIMEIs = []string{"867250300000001"}
	if err := config.Validate(); err != nil {
		t.Fatalf("expected hardened config to pass: %v", err)
	}
	config.CommandToken = "change-me-command-change-me-command"
	if err := config.Validate(); err == nil {
		t.Fatal("expected placeholder token to fail")
	}
}

func TestCommandProtocolFieldsRejectFrameInjection(t *testing.T) {
	body := []byte(`{"imei":"867250300000001","request_id":"safe#,*CMDS"}`)
	err := validateCommandJSON(http.MethodPost, "/commands/reboot", body)
	if err == nil {
		t.Fatal("expected protocol delimiter injection to be rejected")
	}
	if !errors.Is(err, errInvalidCommand) {
		t.Fatalf("unexpected error: %v", err)
	}
}

func FuzzSignedCommandJSON(f *testing.F) {
	f.Add("867250300000001", "request-1")
	f.Fuzz(func(t *testing.T, imei, requestID string) {
		body := []byte(fmt.Sprintf(`{"imei":%q,"request_id":%q}`, imei, requestID))
		_ = validateCommandJSON(http.MethodPost, "/commands/reboot", body)
	})
}
