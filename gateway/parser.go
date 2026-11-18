package main

import (
	"log"
	"strconv"
	"strings"
	"sync"
	"time"
)

type UnlockResult struct {
	IMEI     string
	UserID   string
	UnixTime int64
	Result   int
	DeviceTS string
}

type GPSPayload struct {
	Valid     bool
	Lat       float64
	Lng       float64
	Sat       int
	Hdop      float64
	Alt       float64
	DeviceUTC string
	Date      string
	Mode      string
}

type WifiAccessPoint struct {
	MAC  string `json:"mac,omitempty"`
	RSSI *int   `json:"rssi,omitempty"`
	Raw  string `json:"raw"`
}

type WifiPositioningPayload struct {
	Mode         int               `json:"mode"`
	ScanSeconds  int               `json:"scan_seconds"`
	AccessPoints []WifiAccessPoint `json:"wifi_aps"`
}

type TrackResponsePayload struct {
	IntervalSeconds int `json:"interval_seconds"`
}

type ExternalDeviceResultPayload struct {
	Operation   int    `json:"operation"`
	ResultCode  int    `json:"result_code"`
	ResultLabel string `json:"result_label,omitempty"`
}

type CableLockInfoPayload struct {
	FirmwareVersion string   `json:"firmware_version,omitempty"`
	Payload         []string `json:"payload,omitempty"`
}

type BeaconScanPayload struct {
	Found           bool     `json:"found"`
	BeaconTypeCode  *int     `json:"beacon_type_code,omitempty"`
	BeaconMAC       string   `json:"beacon_mac,omitempty"`
	BeaconTimestamp *int64   `json:"beacon_timestamp,omitempty"`
	BatteryPercent  *int     `json:"battery_percent,omitempty"`
	Longitude       *float64 `json:"longitude,omitempty"`
	Latitude        *float64 `json:"latitude,omitempty"`
	Reserve         string   `json:"reserve,omitempty"`
}

type RFIDUnlockRequestPayload struct {
	Action     int    `json:"action"`
	Reserved   string `json:"reserved,omitempty"`
	CardNumber string `json:"card_number"`
}

type RFIDAdminCardPayload struct {
	CardNumber string `json:"card_number"`
}

type inflightEntry struct {
	ch        chan UnlockResult
	requestID string
}

type InflightRegistry struct {
	mu    sync.Mutex
	items map[string]inflightEntry
}

func NewInflightRegistry() *InflightRegistry {
	return &InflightRegistry{items: make(map[string]inflightEntry)}
}

func (r *InflightRegistry) GetOrCreate(key, requestID string) chan UnlockResult {
	r.mu.Lock()
	defer r.mu.Unlock()
	if entry, ok := r.items[key]; ok {
		if entry.requestID == "" && requestID != "" {
			entry.requestID = requestID
			r.items[key] = entry
		}
		return entry.ch
	}
	ch := make(chan UnlockResult, 1)
	r.items[key] = inflightEntry{ch: ch, requestID: requestID}
	return ch
}

func (r *InflightRegistry) Remove(key string) {
	r.mu.Lock()
	delete(r.items, key)
	r.mu.Unlock()
}

func (r *InflightRegistry) Send(key string, result UnlockResult) (string, bool) {
	r.mu.Lock()
	entry, ok := r.items[key]
	r.mu.Unlock()
	if !ok {
		return "", false
	}
	select {
	case entry.ch <- result:
		return entry.requestID, true
	default:
		return entry.requestID, false
	}
}

func inflightKey(imei, userID string, unixTime int64) string {
	return strings.Join([]string{imei, userID, strconv.FormatInt(unixTime, 10)}, ":")
}

func (g *Gateway) handleMessage(conn *DeviceConn, msg Message) {
	switch msg.Command {
	case "D0", "W0", "L0", "L1":
		g.sendAck(conn, msg.IMEI, msg.Command)
	}

	if msg.Command == "Q0" {
		voltage, ok := parseVoltagePayload(msg.Payload)
		if !ok {
			log.Printf("invalid Q0 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}
		log.Printf("Q0 ONLINE imei=%s voltage=%d", maskIMEI(msg.IMEI), voltage)
		log.Printf("EVENT device.online emit source=Q0 imei=%s voltage=%d", maskIMEI(msg.IMEI), voltage)
		g.emitEvent("device.online", map[string]any{
			"imei":            msg.IMEI,
			"device_ts":       msg.DeviceTS,
			"received_at":     time.Now().UTC().Format(time.RFC3339),
			"battery_voltage": voltage,
		})
	}

	if msg.Command == "H0" {
		lockStatus, voltage, signal, ok := parseHeartbeatPayload(msg.Payload)
		if !ok {
			log.Printf("invalid H0 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}
		log.Printf("H0 HEARTBEAT imei=%s lock=%d voltage=%d signal=%d", maskIMEI(msg.IMEI), lockStatus, voltage, signal)
		g.emitEvent("telemetry.heartbeat", map[string]any{
			"imei":            msg.IMEI,
			"device_ts":       msg.DeviceTS,
			"received_at":     time.Now().UTC().Format(time.RFC3339),
			"lock_status":     lockStatus,
			"battery_voltage": voltage,
			"signal_strength": signal,
		})
	}

	if msg.Command == "S5" {
		status, ok := parseStatusPayload(msg.Payload)
		if !ok {
			log.Printf("invalid S5 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}
		log.Printf("S5 STATUS imei=%s lock=%d voltage=%d signal=%d gps_sat=%d", msg.IMEI, status.LockStatus, status.Voltage, status.Signal, status.GpsSat)
		g.emitEvent("telemetry.status", map[string]any{
			"imei":            msg.IMEI,
			"device_ts":       msg.DeviceTS,
			"received_at":     time.Now().UTC().Format(time.RFC3339),
			"lock_status":     status.LockStatus,
			"battery_voltage": status.Voltage,
			"signal_strength": status.Signal,
			"gps_sat":         status.GpsSat,
		})
	}

	if msg.Command == "D0" {
		gps, ok := parseGPSPayload(msg.Payload)
		if !ok {
			log.Printf("invalid GPS payload imei=%s cmd=%s", maskIMEI(msg.IMEI), msg.Command)
			return
		}

		log.Printf("GPS %s imei=%s valid=%t sat=%d", msg.Command, maskIMEI(msg.IMEI), gps.Valid, gps.Sat)
		g.emitEvent("telemetry.gps", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"source":      msg.Command,
			"lat":         gps.Lat,
			"lng":         gps.Lng,
			"is_valid":    gps.Valid,
			"sat":         gps.Sat,
			"hdop":        gps.Hdop,
			"alt_m":       gps.Alt,
			"device_utc":  gps.DeviceUTC,
			"date":        gps.Date,
			"mode":        gps.Mode,
		})
	}

	if msg.Command == "D1" {
		track, ok := parseTrackResponsePayload(msg.Payload)
		if !ok {
			log.Printf("invalid D1 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}

		log.Printf("D1 TRACK RESULT imei=%s interval_seconds=%d", msg.IMEI, track.IntervalSeconds)
		g.emitEvent("command.track.result", map[string]any{
			"imei":             msg.IMEI,
			"device_ts":        msg.DeviceTS,
			"received_at":      time.Now().UTC().Format(time.RFC3339),
			"interval_seconds": track.IntervalSeconds,
			"payload":          msg.Payload,
		})
	}

	if msg.Command == "D2" {
		wifi, ok := parseWifiPositioningPayload(msg.Payload)
		if !ok {
			log.Printf("invalid D2 wifi payload imei=%s", maskIMEI(msg.IMEI))
			return
		}

		log.Printf("D2 WIFI POSITIONING imei=%s mode=%d scan_seconds=%d aps=%d",
			msg.IMEI, wifi.Mode, wifi.ScanSeconds, len(wifi.AccessPoints))
		g.emitEvent("telemetry.wifi_positioning", map[string]any{
			"imei":         msg.IMEI,
			"device_ts":    msg.DeviceTS,
			"received_at":  time.Now().UTC().Format(time.RFC3339),
			"mode":         wifi.Mode,
			"scan_seconds": wifi.ScanSeconds,
			"wifi_aps":     wifi.AccessPoints,
		})
	}

	if msg.Command == "W0" {
		alarmType, ok := parseAlarmPayload(msg.Payload)
		if !ok {
			log.Printf("invalid W0 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}
		log.Printf("W0 ALARM imei=%s type=%d", msg.IMEI, alarmType)
		g.emitEvent("alarm.triggered", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"type":        alarmType,
		})
	}

	if msg.Command == "L0" {
		result, userID, unixTime, ok := parseL0Payload(msg.Payload)
		if !ok {
			log.Printf("invalid L0 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}
		res := UnlockResult{
			IMEI:     msg.IMEI,
			UserID:   userID,
			UnixTime: unixTime,
			Result:   result,
			DeviceTS: msg.DeviceTS,
		}
		key := inflightKey(msg.IMEI, userID, unixTime)
		requestID, _ := g.inflight.Send(key, res)
		log.Printf("L0 RESPONSE imei=%s unix_time=%d result=%d request_id=%s",
			maskIMEI(msg.IMEI), unixTime, result, requestID)
		g.emitEvent("command.unlock.result", map[string]any{
			"imei":       msg.IMEI,
			"user_id":    userID,
			"unix_time":  unixTime,
			"result":     result,
			"device_ts":  msg.DeviceTS,
			"request_id": requestID,
		})
	}

	if msg.Command == "L1" {
		userID, startTime, rideMinutes, opUnix, ok := parseL1Payload(msg.Payload)
		if !ok {
			log.Printf("invalid L1 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}
		log.Printf("L1 RIDE END imei=%s minutes=%d", maskIMEI(msg.IMEI), rideMinutes)
		g.emitEvent("ride.ended", map[string]any{
			"imei":         msg.IMEI,
			"user_id":      userID,
			"device_ts":    msg.DeviceTS,
			"start_time":   startTime,
			"op_unix":      opUnix,
			"ride_minutes": rideMinutes,
			"received_at":  time.Now().UTC().Format(time.RFC3339),
		})
		g.emitEvent("lock.l1_received", map[string]any{
			"imei":         msg.IMEI,
			"user_id":      userID,
			"device_ts":    msg.DeviceTS,
			"start_time":   startTime,
			"op_unix":      opUnix,
			"ride_minutes": rideMinutes,
			"received_at":  time.Now().UTC().Format(time.RFC3339),
		})
	}

	if msg.Command == "S8" {
		g.emitEvent("command.ring.result", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"payload":     msg.Payload,
		})
	}

	if msg.Command == "G0" {
		info := parseDeviceInfo(msg.Payload)
		g.emitEvent("device.info", map[string]any{
			"imei":                msg.IMEI,
			"device_ts":           msg.DeviceTS,
			"received_at":         time.Now().UTC().Format(time.RFC3339),
			"device_code":         info.DeviceCode,
			"firmware_version":    info.FirmwareVersion,
			"firmware_build_date": info.BuildDate,
			"payload":             msg.Payload,
		})
	}

	if msg.Command == "G1" {
		info, ok := parseCableLockInfoPayload(msg.Payload)
		if !ok {
			log.Printf("invalid G1 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}

		log.Printf("G1 CABLE LOCK INFO imei=%s firmware_version=%s", msg.IMEI, info.FirmwareVersion)
		g.emitEvent("device.cable_lock_info", map[string]any{
			"imei":             msg.IMEI,
			"device_ts":        msg.DeviceTS,
			"received_at":      time.Now().UTC().Format(time.RFC3339),
			"firmware_version": info.FirmwareVersion,
			"payload":          info.Payload,
		})
	}

	if msg.Command == "K0" {
		key := parseBleKey(msg.Payload)
		g.emitEvent("device.ble_key", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"key_value":   key,
		})
	}

	if msg.Command == "I0" {
		iccid := parseSingleField(msg.Payload)
		g.emitEvent("device.iccid", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"sim_iccid":   iccid,
		})
	}

	if msg.Command == "M0" {
		bleMac := parseSingleField(msg.Payload)
		g.emitEvent("device.ble_mac", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"ble_mac":     bleMac,
		})
	}

	if msg.Command == "U0" || msg.Command == "U1" || msg.Command == "U2" {
		g.emitEvent("ota.status", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"cmd":         msg.Command,
			"payload":     msg.Payload,
		})
	}

	if msg.Command == "L5" {
		result, ok := parseExternalDeviceResultPayload(msg.Payload)
		if !ok {
			log.Printf("invalid L5 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}

		log.Printf("L5 EXTERNAL DEVICE RESULT imei=%s operation=%d result=%d", msg.IMEI, result.Operation, result.ResultCode)
		g.emitEvent("command.external_lock.result", map[string]any{
			"imei":         msg.IMEI,
			"device_ts":    msg.DeviceTS,
			"received_at":  time.Now().UTC().Format(time.RFC3339),
			"operation":    result.Operation,
			"result_code":  result.ResultCode,
			"result_label": result.ResultLabel,
			"payload":      msg.Payload,
		})
	}

	if msg.Command == "B0" {
		beacon, ok := parseBeaconScanPayload(msg.Payload)
		if !ok {
			log.Printf("invalid B0 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}

		log.Printf("B0 BEACON SCAN imei=%s found=%t mac=%s", msg.IMEI, beacon.Found, beacon.BeaconMAC)
		g.emitEvent("beacon.scan.result", map[string]any{
			"imei":             msg.IMEI,
			"device_ts":        msg.DeviceTS,
			"received_at":      time.Now().UTC().Format(time.RFC3339),
			"found":            beacon.Found,
			"beacon_type_code": beacon.BeaconTypeCode,
			"beacon_mac":       beacon.BeaconMAC,
			"beacon_timestamp": beacon.BeaconTimestamp,
			"battery_percent":  beacon.BatteryPercent,
			"longitude":        beacon.Longitude,
			"latitude":         beacon.Latitude,
			"reserve":          beacon.Reserve,
			"payload":          msg.Payload,
		})
	}

	if msg.Command == "C0" {
		rfid, ok := parseRFIDUnlockRequestPayload(msg.Payload)
		if !ok {
			log.Printf("invalid C0 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}

		log.Printf("C0 RFID UNLOCK REQUEST imei=%s action=%d", maskIMEI(msg.IMEI), rfid.Action)
		g.emitEvent("rfid.unlock.request", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"action":      rfid.Action,
			"reserved":    rfid.Reserved,
			"card_number": rfid.CardNumber,
			"payload":     msg.Payload,
		})
	}

	if msg.Command == "C1" {
		adminCard, ok := parseRFIDAdminCardPayload(msg.Payload)
		if !ok {
			log.Printf("invalid C1 payload imei=%s", maskIMEI(msg.IMEI))
			return
		}

		log.Printf("C1 RFID ADMIN CARD imei=%s", maskIMEI(msg.IMEI))
		g.emitEvent("rfid.admin_card.result", map[string]any{
			"imei":        msg.IMEI,
			"device_ts":   msg.DeviceTS,
			"received_at": time.Now().UTC().Format(time.RFC3339),
			"card_number": adminCard.CardNumber,
			"payload":     msg.Payload,
		})
	}
}

func (g *Gateway) sendAck(conn *DeviceConn, imei, cmd string) {
	if imei == "" {
		return
	}
	frame := BuildAckFrame(g.cfg.OM, imei, cmd, time.Now().UTC())
	if err := conn.WriteFrame(frame, true, g.cfg.WriteTimeout); err != nil {
		log.Printf("ACK write error imei=%s cmd=%s: %v", maskIMEI(imei), cmd, err)
		g.unregisterDevice(conn)
		_ = conn.Close()
		return
	}
	log.Printf("ACK SENT imei=%s cmd=%s", maskIMEI(imei), cmd)
}

func parseL0Payload(payload []string) (int, string, int64, bool) {
	if len(payload) < 3 {
		return 0, "", 0, false
	}

	result, err := strconv.Atoi(payload[0])
	if err != nil {
		return 0, "", 0, false
	}

	userID := payload[1]
	unixTime, err := strconv.ParseInt(payload[2], 10, 64)
	if err != nil {
		return 0, "", 0, false
	}

	return result, userID, unixTime, true
}

func parseVoltagePayload(payload []string) (int, bool) {
	if len(payload) < 1 {
		return 0, false
	}
	voltage, err := strconv.Atoi(payload[0])
	if err != nil {
		return 0, false
	}
	return voltage, true
}

func parseHeartbeatPayload(payload []string) (int, int, int, bool) {
	if len(payload) < 3 {
		return 0, 0, 0, false
	}
	lockStatus, err := strconv.Atoi(payload[0])
	if err != nil {
		return 0, 0, 0, false
	}
	voltage, err := strconv.Atoi(payload[1])
	if err != nil {
		return 0, 0, 0, false
	}
	signal, err := strconv.Atoi(payload[2])
	if err != nil {
		return 0, 0, 0, false
	}
	return lockStatus, voltage, signal, true
}

type StatusPayload struct {
	LockStatus int
	Voltage    int
	Signal     int
	GpsSat     int
}

func parseStatusPayload(payload []string) (StatusPayload, bool) {
	if len(payload) < 4 {
		return StatusPayload{}, false
	}
	voltage, err := strconv.Atoi(payload[0])
	if err != nil {
		return StatusPayload{}, false
	}
	signal, err := strconv.Atoi(payload[1])
	if err != nil {
		return StatusPayload{}, false
	}
	gpsSat, err := strconv.Atoi(payload[2])
	if err != nil {
		return StatusPayload{}, false
	}
	lockStatus, err := strconv.Atoi(payload[3])
	if err != nil {
		return StatusPayload{}, false
	}
	return StatusPayload{
		LockStatus: lockStatus,
		Voltage:    voltage,
		Signal:     signal,
		GpsSat:     gpsSat,
	}, true
}

func parseAlarmPayload(payload []string) (int, bool) {
	if len(payload) < 1 {
		return 0, false
	}
	alarmType, err := strconv.Atoi(payload[0])
	if err != nil {
		return 0, false
	}
	return alarmType, true
}

type DeviceInfoPayload struct {
	DeviceCode      string
	FirmwareVersion string
	BuildDate       string
}

func parseDeviceInfo(payload []string) DeviceInfoPayload {
	info := DeviceInfoPayload{}
	if len(payload) > 0 {
		info.DeviceCode = payload[0]
	}
	if len(payload) > 1 {
		info.FirmwareVersion = payload[1]
	}
	if len(payload) > 2 {
		info.BuildDate = payload[2]
	}
	return info
}

func parseBleKey(payload []string) string {
	if len(payload) == 0 {
		return ""
	}
	return payload[0]
}

func parseSingleField(payload []string) string {
	if len(payload) == 0 {
		return ""
	}
	return payload[0]
}

func parseL1Payload(payload []string) (string, string, int, int64, bool) {
	if len(payload) < 3 {
		return "", "", 0, 0, false
	}
	userID := payload[0]
	startTime := payload[1]
	minutes, err := strconv.Atoi(payload[2])
	if err != nil {
		return "", "", 0, 0, false
	}
	opUnix := int64(0)
	if parsed, err := strconv.ParseInt(startTime, 10, 64); err == nil {
		opUnix = parsed
	}
	return userID, startTime, minutes, opUnix, true
}

func parseTrackResponsePayload(payload []string) (TrackResponsePayload, bool) {
	if len(payload) < 1 {
		return TrackResponsePayload{}, false
	}

	intervalSeconds, err := strconv.Atoi(strings.TrimSpace(payload[0]))
	if err != nil {
		return TrackResponsePayload{}, false
	}

	return TrackResponsePayload{IntervalSeconds: intervalSeconds}, true
}

func parseExternalDeviceResultPayload(payload []string) (ExternalDeviceResultPayload, bool) {
	if len(payload) < 2 {
		return ExternalDeviceResultPayload{}, false
	}

	operation, err := strconv.Atoi(strings.TrimSpace(payload[0]))
	if err != nil {
		return ExternalDeviceResultPayload{}, false
	}

	resultCode, err := strconv.Atoi(strings.TrimSpace(payload[1]))
	if err != nil {
		return ExternalDeviceResultPayload{}, false
	}

	return ExternalDeviceResultPayload{
		Operation:   operation,
		ResultCode:  resultCode,
		ResultLabel: externalDeviceResultLabel(resultCode),
	}, true
}

func externalDeviceResultLabel(resultCode int) string {
	switch resultCode {
	case 0:
		return "success"
	case 1:
		return "failure"
	case 2:
		return "communication_timeout"
	case 16:
		return "locked"
	case 17:
		return "unlocked"
	default:
		return ""
	}
}

func parseCableLockInfoPayload(payload []string) (CableLockInfoPayload, bool) {
	if len(payload) < 4 {
		return CableLockInfoPayload{}, false
	}

	return CableLockInfoPayload{
		FirmwareVersion: strings.TrimSpace(payload[3]),
		Payload:         append([]string(nil), payload...),
	}, true
}

func parseBeaconScanPayload(payload []string) (BeaconScanPayload, bool) {
	if len(payload) < 1 {
		return BeaconScanPayload{}, false
	}

	foundCode, err := strconv.Atoi(strings.TrimSpace(payload[0]))
	if err != nil {
		return BeaconScanPayload{}, false
	}

	result := BeaconScanPayload{
		Found: foundCode == 1,
	}

	if len(payload) > 1 {
		if value, err := strconv.Atoi(strings.TrimSpace(payload[1])); err == nil {
			result.BeaconTypeCode = &value
		}
	}
	if len(payload) > 2 {
		result.BeaconMAC = strings.TrimSpace(payload[2])
	}
	if len(payload) > 3 {
		if value, err := strconv.ParseInt(strings.TrimSpace(payload[3]), 10, 64); err == nil {
			result.BeaconTimestamp = &value
		}
	}
	if len(payload) > 4 {
		if value, err := strconv.Atoi(strings.TrimSpace(payload[4])); err == nil {
			result.BatteryPercent = &value
		}
	}
	if len(payload) > 5 {
		if value, err := strconv.ParseFloat(strings.TrimSpace(payload[5]), 64); err == nil {
			result.Longitude = &value
		}
	}
	if len(payload) > 6 {
		if value, err := strconv.ParseFloat(strings.TrimSpace(payload[6]), 64); err == nil {
			result.Latitude = &value
		}
	}
	if len(payload) > 7 {
		result.Reserve = strings.TrimSpace(payload[7])
	}

	return result, true
}

func parseRFIDUnlockRequestPayload(payload []string) (RFIDUnlockRequestPayload, bool) {
	if len(payload) < 3 {
		return RFIDUnlockRequestPayload{}, false
	}

	action, err := strconv.Atoi(strings.TrimSpace(payload[0]))
	if err != nil {
		return RFIDUnlockRequestPayload{}, false
	}

	return RFIDUnlockRequestPayload{
		Action:     action,
		Reserved:   strings.TrimSpace(payload[1]),
		CardNumber: strings.TrimSpace(payload[2]),
	}, true
}

func parseRFIDAdminCardPayload(payload []string) (RFIDAdminCardPayload, bool) {
	if len(payload) < 1 {
		return RFIDAdminCardPayload{}, false
	}

	cardNumber := strings.TrimSpace(payload[0])
	if cardNumber == "" {
		return RFIDAdminCardPayload{}, false
	}

	return RFIDAdminCardPayload{CardNumber: cardNumber}, true
}

func parseGPSPayload(payload []string) (GPSPayload, bool) {
	if len(payload) < 3 {
		return GPSPayload{}, false
	}

	get := func(idx int) string {
		if idx >= 0 && idx < len(payload) {
			return payload[idx]
		}
		return ""
	}

	deviceUTC := get(1)
	validFlag := get(2)
	if !strings.EqualFold(validFlag, "A") && !strings.EqualFold(validFlag, "VA") {
		return GPSPayload{}, false
	}
	latRaw := get(3)
	latHem := get(4)
	lngRaw := get(5)
	lngHem := get(6)

	valid := strings.EqualFold(validFlag, "A")
	lat := 0.0
	lng := 0.0
	if valid {
		if latRaw == "" || lngRaw == "" {
			return GPSPayload{}, false
		}
		var ok bool
		lat, ok = parseCoordinate(latRaw, 2, latHem)
		if !ok {
			return GPSPayload{}, false
		}
		lng, ok = parseCoordinate(lngRaw, 3, lngHem)
		if !ok {
			return GPSPayload{}, false
		}
	} else if latRaw != "" && lngRaw != "" {
		if parsed, ok := parseCoordinate(latRaw, 2, latHem); ok {
			lat = parsed
		}
		if parsed, ok := parseCoordinate(lngRaw, 3, lngHem); ok {
			lng = parsed
		}
	}

	sat := 0
	if v, err := strconv.Atoi(get(7)); err == nil {
		sat = v
	}
	hdop := 0.0
	if v, err := strconv.ParseFloat(get(8), 64); err == nil {
		hdop = v
	}
	date := get(9)
	alt := 0.0
	if v, err := strconv.ParseFloat(get(10), 64); err == nil {
		alt = v
	}
	mode := get(12)

	return GPSPayload{
		Valid:     valid,
		Lat:       lat,
		Lng:       lng,
		Sat:       sat,
		Hdop:      hdop,
		Alt:       alt,
		DeviceUTC: deviceUTC,
		Date:      date,
		Mode:      mode,
	}, true
}

func parseWifiPositioningPayload(payload []string) (WifiPositioningPayload, bool) {
	if len(payload) < 2 {
		return WifiPositioningPayload{}, false
	}

	mode, err := strconv.Atoi(strings.TrimSpace(payload[0]))
	if err != nil {
		return WifiPositioningPayload{}, false
	}

	scanSeconds, err := strconv.Atoi(strings.TrimSpace(payload[1]))
	if err != nil {
		return WifiPositioningPayload{}, false
	}

	accessPoints := make([]WifiAccessPoint, 0, len(payload)-2)
	for _, rawEntry := range payload[2:] {
		entry := strings.TrimSpace(rawEntry)
		if entry == "" || entry == "0" {
			continue
		}

		ap := WifiAccessPoint{Raw: entry}
		if mac, rssi, ok := parseWifiAccessPoint(entry); ok {
			ap.MAC = mac
			ap.RSSI = &rssi
		}
		accessPoints = append(accessPoints, ap)
	}

	return WifiPositioningPayload{
		Mode:         mode,
		ScanSeconds:  scanSeconds,
		AccessPoints: accessPoints,
	}, true
}

func parseWifiAccessPoint(entry string) (string, int, bool) {
	idx := strings.Index(entry, "-")
	if idx <= 0 || idx >= len(entry)-1 {
		return "", 0, false
	}

	mac := strings.TrimSpace(entry[:idx])
	if mac == "" {
		return "", 0, false
	}

	rssi, err := strconv.Atoi(strings.TrimSpace(entry[idx:]))
	if err != nil {
		return "", 0, false
	}

	return mac, rssi, true
}

func parseCoordinate(value string, degDigits int, hemisphere string) (float64, bool) {
	value = strings.TrimSpace(value)
	if value == "" {
		return 0, false
	}

	// Try NMEA ddmm.mmmm format first.
	if len(value) >= degDigits+1 {
		degPart := value[:degDigits]
		minPart := value[degDigits:]
		deg, err := strconv.Atoi(degPart)
		if err == nil {
			minutes, err := strconv.ParseFloat(minPart, 64)
			if err == nil {
				decimal := float64(deg) + (minutes / 60.0)
				switch strings.ToUpper(hemisphere) {
				case "S", "W":
					decimal = -decimal
				}
				return decimal, true
			}
		}
	}

	// Fallback: assume decimal degrees.
	decimal, err := strconv.ParseFloat(value, 64)
	if err != nil {
		return 0, false
	}
	switch strings.ToUpper(hemisphere) {
	case "S", "W":
		decimal = -abs(decimal)
	case "N", "E":
		decimal = abs(decimal)
	}
	return decimal, true
}

func abs(value float64) float64 {
	if value < 0 {
		return -value
	}
	return value
}
