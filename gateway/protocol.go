package main

import (
	"bufio"
	"bytes"
	"errors"
	"fmt"
	"net"
	"strings"
	"time"
)

const (
	cmdrPrefix = "*CMDR"
	cmdsPrefix = "*CMDS"
)

var (
	errInvalidFrame  = errors.New("invalid frame")
	errFrameTooLarge = errors.New("frame exceeds configured size limit")
)

type Message struct {
	Raw      string
	Om       string
	IMEI     string
	DeviceTS string
	Command  string
	Payload  []string
}

type FrameReader struct {
	reader   *bufio.Reader
	maxBytes int
}

func NewFrameReader(conn net.Conn, maxBytes int) *FrameReader {
	if maxBytes < 256 {
		maxBytes = 4096
	}
	return &FrameReader{
		reader:   bufio.NewReaderSize(conn, maxBytes+1),
		maxBytes: maxBytes,
	}
}

func (fr *FrameReader) ReadFrame(delim byte) (string, error) {
	raw, err := fr.reader.ReadSlice(delim)
	if errors.Is(err, bufio.ErrBufferFull) || len(raw) > fr.maxBytes {
		for !bytes.Contains(raw, []byte{delim}) && errors.Is(err, bufio.ErrBufferFull) {
			raw, err = fr.reader.ReadSlice(delim)
		}
		return "", errFrameTooLarge
	}
	if err != nil {
		return "", err
	}
	return string(raw), nil
}

func ParseCMDR(raw string) (Message, error) {
	msg := strings.TrimSpace(raw)
	if strings.HasPrefix(msg, "\xff\xff") {
		msg = strings.TrimPrefix(msg, "\xff\xff")
		msg = strings.TrimSpace(msg)
	}
	msg = strings.TrimSuffix(msg, "#")

	if !strings.HasPrefix(msg, cmdrPrefix+",") {
		return Message{}, errInvalidFrame
	}

	parts := strings.Split(msg, ",")
	if len(parts) < 5 {
		return Message{}, fmt.Errorf("%w: expected at least 5 parts", errInvalidFrame)
	}
	if len(parts) > 64 {
		return Message{}, fmt.Errorf("%w: too many fields", errInvalidFrame)
	}
	for _, part := range parts {
		if len(part) > 512 || strings.ContainsAny(part, "\r\n#") {
			return Message{}, fmt.Errorf("%w: invalid field", errInvalidFrame)
		}
	}

	return Message{
		Raw:      msg,
		Om:       parts[1],
		IMEI:     parts[2],
		DeviceTS: parts[3],
		Command:  parts[4],
		Payload:  parts[5:],
	}, nil
}

func FormatDeviceTime(t time.Time) string {
	return t.UTC().Format("060102150405")
}

func BuildCommandFrame(om, imei, cmd string, ts time.Time, payload ...string) string {
	fields := []string{cmdsPrefix, om, imei, FormatDeviceTime(ts), cmd}
	fields = append(fields, payload...)
	return strings.Join(fields, ",") + "#\n"
}

func BuildAckFrame(om, imei, ackFor string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "Re", ts, ackFor)
}

func BuildUnlockFrame(om, imei string, ts time.Time, resetFlag int, userID string, unixTime int64) string {
	payload := []string{fmt.Sprintf("%d", resetFlag), userID, fmt.Sprintf("%d", unixTime)}
	return BuildCommandFrame(om, imei, "L0", ts, payload...)
}

func BuildGPSOnceFrame(om, imei string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "D0", ts)
}

func BuildTrackingFrame(om, imei string, ts time.Time, seconds int) string {
	return BuildCommandFrame(om, imei, "D1", ts, fmt.Sprintf("%d", seconds))
}

func BuildWifiPositioningFrame(om, imei string, ts time.Time, mode, scanSeconds int) string {
	return BuildCommandFrame(om, imei, "D2", ts, fmt.Sprintf("%d", mode), fmt.Sprintf("%d", scanSeconds))
}

func BuildStatusFrame(om, imei string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "S5", ts)
}

func BuildPowerOffFrame(om, imei string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "S0", ts)
}

func BuildRebootFrame(om, imei string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "S1", ts)
}

func BuildRingFrame(om, imei string, ts time.Time, ringTimes int) string {
	payload := []string{fmt.Sprintf("%d", ringTimes), "0"}
	return BuildCommandFrame(om, imei, "S8", ts, payload...)
}

func BuildDeviceInfoFrame(om, imei string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "G0", ts)
}

func BuildCableLockInfoFrame(om, imei string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "G1", ts)
}

func BuildBleKeyFrame(om, imei string, ts time.Time, mode int, key string) string {
	payload := []string{fmt.Sprintf("%d", mode)}
	if key != "" {
		payload = append(payload, key)
	}
	return BuildCommandFrame(om, imei, "K0", ts, payload...)
}

func BuildSimInfoFrame(om, imei string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "I0", ts)
}

func BuildBleMacFrame(om, imei string, ts time.Time) string {
	return BuildCommandFrame(om, imei, "M0", ts)
}

func BuildExternalDeviceControlFrame(om, imei string, ts time.Time, operation int) string {
	return BuildCommandFrame(om, imei, "L5", ts, fmt.Sprintf("%d", operation))
}

func BuildBeaconValidationFrame(om, imei string, ts time.Time, result int) string {
	return BuildCommandFrame(om, imei, "B0", ts, fmt.Sprintf("%d", result))
}

func BuildRFIDAdminCardFrame(om, imei string, ts time.Time, operation int, cardNumber string) string {
	return BuildCommandFrame(om, imei, "C1", ts, fmt.Sprintf("%d", operation), cardNumber)
}

func BuildOtaFrame(om, imei string, ts time.Time, cmd string, payload []string) string {
	return BuildCommandFrame(om, imei, cmd, ts, payload...)
}
