package main

import (
	"errors"
	"net"
	"strings"
	"testing"
)

func TestFrameReaderRejectsOversizedFrames(t *testing.T) {
	server, client := net.Pipe()
	defer server.Close()
	defer client.Close()
	reader := NewFrameReader(server, 256)
	go func() {
		_, _ = client.Write([]byte(strings.Repeat("A", 300) + "#"))
	}()
	if _, err := reader.ReadFrame('#'); !errors.Is(err, errFrameTooLarge) {
		t.Fatalf("expected oversized frame error, got %v", err)
	}
}

func FuzzParseCMDR(f *testing.F) {
	f.Add("*CMDR,OM,867250300000001,260715120000,H0,1,400,20#")
	f.Add("garbage")
	f.Fuzz(func(t *testing.T, raw string) {
		_, _ = ParseCMDR(raw)
	})
}

func TestParseWifiPositioningPayload(t *testing.T) {
	payload := []string{
		"1",
		"3",
		"A1B1C1D1E1F1-30",
		"0",
		"A2B2C2D2E2F2-40",
	}

	parsed, ok := parseWifiPositioningPayload(payload)
	if !ok {
		t.Fatal("expected D2 wifi payload to parse")
	}

	if parsed.Mode != 1 {
		t.Fatalf("expected mode 1, got %d", parsed.Mode)
	}

	if parsed.ScanSeconds != 3 {
		t.Fatalf("expected scan seconds 3, got %d", parsed.ScanSeconds)
	}

	if len(parsed.AccessPoints) != 2 {
		t.Fatalf("expected 2 wifi access points, got %d", len(parsed.AccessPoints))
	}

	if parsed.AccessPoints[0].MAC != "A1B1C1D1E1F1" {
		t.Fatalf("unexpected first MAC: %q", parsed.AccessPoints[0].MAC)
	}

	if parsed.AccessPoints[0].RSSI == nil || *parsed.AccessPoints[0].RSSI != -30 {
		t.Fatalf("unexpected first RSSI: %#v", parsed.AccessPoints[0].RSSI)
	}
}

func TestParseGPSPayloadRejectsWifiPositioningPayload(t *testing.T) {
	payload := []string{
		"1",
		"3",
		"A1B1C1D1E1F1-30",
	}

	if _, ok := parseGPSPayload(payload); ok {
		t.Fatal("expected D2 wifi payload to be rejected by GPS parser")
	}
}

func TestParseTrackResponsePayload(t *testing.T) {
	parsed, ok := parseTrackResponsePayload([]string{"60"})
	if !ok {
		t.Fatal("expected D1 payload to parse")
	}

	if parsed.IntervalSeconds != 60 {
		t.Fatalf("expected interval 60, got %d", parsed.IntervalSeconds)
	}
}

func TestParseExternalDeviceResultPayload(t *testing.T) {
	parsed, ok := parseExternalDeviceResultPayload([]string{"36", "16"})
	if !ok {
		t.Fatal("expected L5 payload to parse")
	}

	if parsed.Operation != 36 {
		t.Fatalf("expected operation 36, got %d", parsed.Operation)
	}

	if parsed.ResultCode != 16 {
		t.Fatalf("expected result code 16, got %d", parsed.ResultCode)
	}

	if parsed.ResultLabel != "locked" {
		t.Fatalf("expected locked label, got %q", parsed.ResultLabel)
	}
}

func TestParseCableLockInfoPayload(t *testing.T) {
	parsed, ok := parseCableLockInfoPayload([]string{"0", "0", "0", "110", "0"})
	if !ok {
		t.Fatal("expected G1 payload to parse")
	}

	if parsed.FirmwareVersion != "110" {
		t.Fatalf("expected firmware version 110, got %q", parsed.FirmwareVersion)
	}
}

func TestParseBeaconScanPayload(t *testing.T) {
	payload := []string{"1", "10", "12:34:56:78:90:AB", "1578386704", "50", "36.8219", "-1.2921", "0"}

	parsed, ok := parseBeaconScanPayload(payload)
	if !ok {
		t.Fatal("expected B0 payload to parse")
	}

	if !parsed.Found {
		t.Fatal("expected beacon to be found")
	}

	if parsed.BeaconTypeCode == nil || *parsed.BeaconTypeCode != 10 {
		t.Fatalf("unexpected beacon type: %#v", parsed.BeaconTypeCode)
	}

	if parsed.BeaconMAC != "12:34:56:78:90:AB" {
		t.Fatalf("unexpected beacon mac: %q", parsed.BeaconMAC)
	}

	if parsed.Longitude == nil || *parsed.Longitude != 36.8219 {
		t.Fatalf("unexpected longitude: %#v", parsed.Longitude)
	}

	if parsed.Latitude == nil || *parsed.Latitude != -1.2921 {
		t.Fatalf("unexpected latitude: %#v", parsed.Latitude)
	}
}

func TestParseRFIDUnlockRequestPayload(t *testing.T) {
	parsed, ok := parseRFIDUnlockRequestPayload([]string{"0", "0", "000000001A2B3C4D"})
	if !ok {
		t.Fatal("expected C0 payload to parse")
	}

	if parsed.Action != 0 {
		t.Fatalf("expected action 0, got %d", parsed.Action)
	}

	if parsed.CardNumber != "000000001A2B3C4D" {
		t.Fatalf("unexpected card number: %q", parsed.CardNumber)
	}
}

func TestParseRFIDAdminCardPayload(t *testing.T) {
	parsed, ok := parseRFIDAdminCardPayload([]string{"000000001A2B3C4D"})
	if !ok {
		t.Fatal("expected C1 payload to parse")
	}

	if parsed.CardNumber != "000000001A2B3C4D" {
		t.Fatalf("unexpected card number: %q", parsed.CardNumber)
	}
}
