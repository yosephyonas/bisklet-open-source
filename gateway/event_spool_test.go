package main

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"strconv"
	"strings"
	"testing"
	"time"
)

func TestEventSpoolRecoversPersistedEvents(t *testing.T) {
	directory := t.TempDir()
	first, err := NewEventSpool(directory, 1, 1000)
	if err != nil {
		t.Fatal(err)
	}
	event := outboundEvent{
		Event:      "ride.ended",
		EventID:    "event-001",
		Payload:    map[string]any{"imei": "867250300000001"},
		OccurredAt: time.Now().UTC(),
	}
	if err := first.Enqueue(event); err != nil {
		t.Fatal(err)
	}

	restarted, err := NewEventSpool(directory, 1, 1000)
	if err != nil {
		t.Fatal(err)
	}
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()
	restarted.Start(ctx)
	recovered, ok := restarted.Next(ctx)
	if !ok || recovered.EventID != event.EventID {
		t.Fatalf("expected event to survive restart, got %#v", recovered)
	}
	if err := restarted.Complete(recovered); err != nil {
		t.Fatal(err)
	}
	if pending := restarted.Pending(); pending != 0 {
		t.Fatalf("expected empty spool, got %d pending events", pending)
	}
}

func TestWebhookDeliveryIsSigned(t *testing.T) {
	secret := strings.Repeat("x", 32) // Synthetic test-only key
	var signatureValid bool
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, request *http.Request) {
		body, _ := io.ReadAll(request.Body)
		timestamp := request.Header.Get(timestampHeader)
		nonce := request.Header.Get(nonceHeader)
		provided := request.Header.Get(signatureHeader)
		_, timestampErr := strconv.ParseInt(timestamp, 10, 64)
		expected := signRequest(secret, timestamp, nonce, request.Method, requestTarget(request.URL), body)
		signatureValid = timestampErr == nil && nonce == "event-002" && provided == expected
		w.WriteHeader(http.StatusNoContent)
	}))
	defer server.Close()

	gateway, err := NewGateway(Config{
		EventWebhookURL:   server.URL + "/api/v1/lock/events",
		EventWebhookToken: secret,
		EventHTTPTimeout:  time.Second,
		EventQueueSize:    1,
	})
	if err != nil {
		t.Fatal(err)
	}
	event := outboundEvent{
		Event:      "ride.ended",
		EventID:    "event-002",
		Payload:    map[string]any{"imei": "867250300000001"},
		OccurredAt: time.Now().UTC(),
	}
	if !gateway.deliverEvent(context.Background(), 1, event) {
		t.Fatal("expected webhook delivery to succeed")
	}
	if !signatureValid {
		t.Fatal("expected webhook request signature to verify")
	}
}
