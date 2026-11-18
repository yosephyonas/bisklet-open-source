package main

import (
	"fmt"
	"net/http"
	"net/http/httptest"
	"strconv"
	"testing"
	"time"
)

func TestCommandAuthRequiresConfiguredToken(t *testing.T) {
	g, err := NewGateway(Config{
		CommandToken:        "0123456789abcdef0123456789abcdef",
		EventHTTPTimeout:    time.Second,
		EventQueueSize:      1,
		EventWorkers:        1,
		CommandMaxBodyBytes: 32768,
		CommandClockSkew:    time.Minute,
	})
	if err != nil {
		t.Fatal(err)
	}

	handler := g.requireCommandAuth(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	})

	req := httptest.NewRequest(http.MethodGet, "/devices/live", nil)
	resp := httptest.NewRecorder()
	handler(resp, req)

	if resp.Code != http.StatusUnauthorized {
		t.Fatalf("expected unauthorized without token, got %d", resp.Code)
	}

	req = httptest.NewRequest(http.MethodGet, "/devices/live", nil)
	signTestRequest(req, "0123456789abcdef0123456789abcdef", []byte{})
	resp = httptest.NewRecorder()
	handler(resp, req)

	if resp.Code != http.StatusNoContent {
		t.Fatalf("expected request with token to pass, got %d", resp.Code)
	}
}

func TestCommandAuthFailsClosedWhenUnset(t *testing.T) {
	g, err := NewGateway(Config{
		EventHTTPTimeout:    time.Second,
		EventQueueSize:      1,
		EventWorkers:        1,
		CommandMaxBodyBytes: 32768,
		CommandClockSkew:    time.Minute,
	})
	if err != nil {
		t.Fatal(err)
	}

	handler := g.requireCommandAuth(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	})

	req := httptest.NewRequest(http.MethodGet, "/devices/live", nil)
	resp := httptest.NewRecorder()
	handler(resp, req)

	if resp.Code != http.StatusServiceUnavailable {
		t.Fatalf("expected request to fail closed when token is unset, got %d", resp.Code)
	}
}

func signTestRequest(req *http.Request, secret string, body []byte) {
	timestamp := strconv.FormatInt(time.Now().UTC().Unix(), 10)
	nonce := fmt.Sprintf("test-%d", time.Now().UnixNano())
	req.Header.Set(timestampHeader, timestamp)
	req.Header.Set(nonceHeader, nonce)
	req.Header.Set(signatureHeader, signRequest(secret, timestamp, nonce, req.Method, requestTarget(req.URL), body))
}
