package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	rand "math/rand/v2"
	"net"
	"net/http"
	"os"
	"strconv"
	"strings"
	"sync"
	"time"
)

var (
	ErrOffline      = errors.New("device offline")
	ErrTimeout      = errors.New("command timeout")
	ErrServerClosed = http.ErrServerClosed
)

type Config struct {
	TCPAddr             string
	HTTPAddr            string
	OM                  string
	Environment         string
	ReadTimeout         time.Duration
	MaxIdleTimeouts     int
	WriteTimeout        time.Duration
	MaxFrameBytes       int
	MaxConnections      int
	MaxConnectionsIP    int
	AllowedIMEIs        []string
	AllowedDeviceCIDRs  []string
	CommandTimeout      time.Duration
	CommandRetries      int
	CommandToken        string
	CommandMaxBodyBytes int64
	CommandClockSkew    time.Duration
	EventWebhookURL     string
	EventWebhookToken   string
	EventHTTPTimeout    time.Duration
	EventRetries        int
	EventRetryDelay     time.Duration
	EventQueueSize      int
	EventWorkers        int
	EventSpoolDir       string
	EventSpoolMaxFiles  int
	ShutdownTimeout     time.Duration
	DebugFrames         bool
}

func loadConfig() Config {
	return Config{
		TCPAddr:             getEnv("TCP_ADDR", ":9000"),
		HTTPAddr:            getEnv("HTTP_ADDR", "127.0.0.1:9001"),
		OM:                  getEnv("OMNI_OM", "OM"),
		Environment:         strings.ToLower(getEnv("GATEWAY_ENV", "production")),
		ReadTimeout:         getEnvDuration("READ_TIMEOUT_SEC", 120*time.Second),
		MaxIdleTimeouts:     getEnvInt("MAX_IDLE_TIMEOUTS", 3),
		WriteTimeout:        getEnvDuration("WRITE_TIMEOUT_SEC", 10*time.Second),
		MaxFrameBytes:       getEnvInt("MAX_FRAME_BYTES", 4096),
		MaxConnections:      getEnvInt("MAX_CONNECTIONS", 5000),
		MaxConnectionsIP:    getEnvInt("MAX_CONNECTIONS_PER_IP", 500),
		AllowedIMEIs:        getEnvList("ALLOWED_IMEIS"),
		AllowedDeviceCIDRs:  getEnvList("ALLOWED_DEVICE_CIDRS"),
		CommandTimeout:      getEnvDuration("COMMAND_TIMEOUT_SEC", 20*time.Second),
		CommandRetries:      getEnvInt("COMMAND_RETRIES", 1),
		CommandToken:        getEnv("COMMAND_TOKEN", os.Getenv("GO_GATEWAY_COMMAND_TOKEN")),
		CommandMaxBodyBytes: int64(getEnvInt("COMMAND_MAX_BODY_BYTES", 32768)),
		CommandClockSkew:    getEnvDuration("COMMAND_CLOCK_SKEW_SEC", 60*time.Second),
		EventWebhookURL:     os.Getenv("LARAVEL_EVENT_WEBHOOK_URL"),
		EventWebhookToken:   os.Getenv("LARAVEL_EVENT_WEBHOOK_TOKEN"),
		EventHTTPTimeout:    getEnvDuration("EVENT_HTTP_TIMEOUT_SEC", 5*time.Second),
		EventRetries:        getEnvInt("EVENT_RETRIES", 2),
		EventRetryDelay:     getEnvDuration("EVENT_RETRY_DELAY_SEC", 1*time.Second),
		EventQueueSize:      getEnvInt("EVENT_QUEUE_SIZE", 1000),
		EventWorkers:        getEnvInt("EVENT_WORKERS", 4),
		EventSpoolDir:       getEnv("EVENT_SPOOL_DIR", "./storage/events"),
		EventSpoolMaxFiles:  getEnvInt("EVENT_SPOOL_MAX_FILES", 100000),
		ShutdownTimeout:     getEnvDuration("SHUTDOWN_TIMEOUT_SEC", 20*time.Second),
		DebugFrames:         getEnvBool("DEBUG_FRAMES", false),
	}
}

type Gateway struct {
	cfg               Config
	devices           *DeviceRegistry
	inflight          *InflightRegistry
	httpClient        *http.Client
	eventSpool        *EventSpool
	replays           *ReplayCache
	connections       *ConnectionLimiter
	allowedIMEIs      map[string]struct{}
	allowedDeviceNets []*net.IPNet
	serverMu          sync.Mutex
	httpServer        *http.Server
	tcpListener       net.Listener
	connWG            sync.WaitGroup
}

func NewGateway(cfg Config) (*Gateway, error) {
	if cfg.EventQueueSize < 1 {
		cfg.EventQueueSize = 1
	}
	if cfg.EventWorkers < 1 {
		cfg.EventWorkers = 1
	}

	spool, err := NewEventSpool(cfg.EventSpoolDir, cfg.EventQueueSize, cfg.EventSpoolMaxFiles)
	if err != nil {
		return nil, err
	}

	g := &Gateway{
		cfg:         cfg,
		devices:     NewDeviceRegistry(),
		inflight:    NewInflightRegistry(),
		replays:     NewReplayCache(cfg.CommandClockSkew * 2),
		connections: NewConnectionLimiter(cfg.MaxConnections, cfg.MaxConnectionsIP),
		httpClient: &http.Client{
			Timeout: cfg.EventHTTPTimeout,
		},
		eventSpool:   spool,
		allowedIMEIs: make(map[string]struct{}, len(cfg.AllowedIMEIs)),
	}
	for _, imei := range cfg.AllowedIMEIs {
		g.allowedIMEIs[imei] = struct{}{}
	}
	for _, rawCIDR := range cfg.AllowedDeviceCIDRs {
		_, network, parseErr := net.ParseCIDR(rawCIDR)
		if parseErr != nil {
			return nil, fmt.Errorf("invalid ALLOWED_DEVICE_CIDRS entry %q: %w", rawCIDR, parseErr)
		}
		g.allowedDeviceNets = append(g.allowedDeviceNets, network)
	}
	return g, nil
}

func (g *Gateway) StartHTTP() error {
	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", g.handleHealthHTTP)
	mux.HandleFunc("/devices/live", g.requireCommandAuth(g.handleLiveDevicesHTTP))
	mux.HandleFunc("/commands/unlock", g.requireCommandAuth(g.handleUnlockHTTP))
	mux.HandleFunc("/commands/gps-once", g.requireCommandAuth(g.handleGPSOnceHTTP))
	mux.HandleFunc("/commands/status", g.requireCommandAuth(g.handleStatusHTTP))
	mux.HandleFunc("/commands/restart-connection", g.requireCommandAuth(g.handleRestartConnectionHTTP))
	mux.HandleFunc("/commands/restart-all-connections", g.requireCommandAuth(g.handleRestartAllConnectionsHTTP))
	mux.HandleFunc("/commands/ring", g.requireCommandAuth(g.handleRingHTTP))
	mux.HandleFunc("/commands/track", g.requireCommandAuth(g.handleTrackHTTP))
	mux.HandleFunc("/commands/power-off", g.requireCommandAuth(g.handlePowerOffHTTP))
	mux.HandleFunc("/commands/reboot", g.requireCommandAuth(g.handleRebootHTTP))
	mux.HandleFunc("/commands/external-device", g.requireCommandAuth(g.handleExternalDeviceHTTP))
	mux.HandleFunc("/commands/device-info", g.requireCommandAuth(g.handleDeviceInfoHTTP))
	mux.HandleFunc("/commands/cable-lock-info", g.requireCommandAuth(g.handleCableLockInfoHTTP))
	mux.HandleFunc("/commands/ble-key", g.requireCommandAuth(g.handleBleKeyHTTP))
	mux.HandleFunc("/commands/sim-iccid", g.requireCommandAuth(g.handleSimInfoHTTP))
	mux.HandleFunc("/commands/ble-mac", g.requireCommandAuth(g.handleBleMacHTTP))
	mux.HandleFunc("/commands/wifi-positioning", g.requireCommandAuth(g.handleWifiPositioningHTTP))
	mux.HandleFunc("/commands/beacon-validation", g.requireCommandAuth(g.handleBeaconValidationHTTP))
	mux.HandleFunc("/commands/rfid-admin-card", g.requireCommandAuth(g.handleRFIDAdminCardHTTP))
	mux.HandleFunc("/commands/ota/detect", g.requireCommandAuth(g.handleOtaDetectHTTP))
	mux.HandleFunc("/commands/ota/start", g.requireCommandAuth(g.handleOtaStartHTTP))
	mux.HandleFunc("/commands/ota/finish", g.requireCommandAuth(g.handleOtaFinishHTTP))

	srv := &http.Server{
		Addr:              g.cfg.HTTPAddr,
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       10 * time.Second,
		WriteTimeout:      30 * time.Second,
		IdleTimeout:       60 * time.Second,
		MaxHeaderBytes:    16 << 10,
	}
	g.serverMu.Lock()
	g.httpServer = srv
	g.serverMu.Unlock()

	log.Printf("HTTP command server listening on %s", g.cfg.HTTPAddr)
	return srv.ListenAndServe()
}

func (g *Gateway) handleHealthHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	pending := g.eventSpool.Pending()
	status := "ok"
	httpStatus := http.StatusOK
	if g.cfg.EventSpoolMaxFiles > 0 && pending >= (g.cfg.EventSpoolMaxFiles*9)/10 {
		status = "degraded"
		httpStatus = http.StatusServiceUnavailable
	}
	writeJSON(w, httpStatus, map[string]any{
		"status":              status,
		"live_devices":        len(g.devices.List()),
		"active_connections":  g.connections.Count(),
		"event_spool_pending": pending,
		"event_spool_max":     g.cfg.EventSpoolMaxFiles,
		"event_queue_cap":     g.cfg.EventQueueSize,
	})
}

type UnlockRequest struct {
	IMEI      string         `json:"imei"`
	UserID    StringOrNumber `json:"user_id"`
	ResetFlag IntOrNumber    `json:"reset_flag"`
	UnixTime  int64          `json:"unix_time"`
	CommandID string         `json:"command_id"`
	RequestID string         `json:"request_id"`
}

type GPSOnceRequest struct {
	IMEI      string `json:"imei"`
	RequestID string `json:"request_id"`
}

type GPSOnceResponse struct {
	IMEI      string `json:"imei"`
	Status    string `json:"status"`
	Error     string `json:"error,omitempty"`
	RequestID string `json:"request_id,omitempty"`
}

type StatusRequest struct {
	IMEI      string `json:"imei"`
	RequestID string `json:"request_id"`
}

type RestartConnectionRequest struct {
	IMEI      string `json:"imei"`
	RequestID string `json:"request_id"`
}

type RestartAllConnectionsRequest struct {
	RequestID string `json:"request_id"`
}

type RestartAllConnectionsResponse struct {
	Status            string `json:"status"`
	Error             string `json:"error,omitempty"`
	RequestID         string `json:"request_id,omitempty"`
	ClosedConnections int    `json:"closed_connections"`
}

type RingRequest struct {
	IMEI      string      `json:"imei"`
	RingTimes IntOrNumber `json:"ring_times"`
	RequestID string      `json:"request_id"`
}

type TrackRequest struct {
	IMEI            string      `json:"imei"`
	IntervalSeconds IntOrNumber `json:"interval_seconds"`
	RequestID       string      `json:"request_id"`
}

type SimpleCommandRequest struct {
	IMEI      string `json:"imei"`
	RequestID string `json:"request_id"`
}

type ExternalDeviceControlRequest struct {
	IMEI      string      `json:"imei"`
	Operation IntOrNumber `json:"operation"`
	RequestID string      `json:"request_id"`
}

type DeviceInfoRequest struct {
	IMEI      string `json:"imei"`
	RequestID string `json:"request_id"`
}

type WifiPositioningRequest struct {
	IMEI        string      `json:"imei"`
	Mode        IntOrNumber `json:"mode"`
	ScanSeconds IntOrNumber `json:"scan_seconds"`
	RequestID   string      `json:"request_id"`
}

type BeaconValidationRequest struct {
	IMEI      string      `json:"imei"`
	Result    IntOrNumber `json:"result"`
	RequestID string      `json:"request_id"`
}

type BleKeyRequest struct {
	IMEI      string      `json:"imei"`
	Mode      IntOrNumber `json:"mode"`
	Key       string      `json:"key"`
	RequestID string      `json:"request_id"`
}

type SimInfoRequest struct {
	IMEI      string `json:"imei"`
	RequestID string `json:"request_id"`
}

type BleMacRequest struct {
	IMEI      string `json:"imei"`
	RequestID string `json:"request_id"`
}

type RFIDAdminCardRequest struct {
	IMEI       string      `json:"imei"`
	Operation  IntOrNumber `json:"operation"`
	CardNumber string      `json:"card_number"`
	RequestID  string      `json:"request_id"`
}

type CommandResponse struct {
	IMEI      string `json:"imei"`
	Status    string `json:"status"`
	Error     string `json:"error,omitempty"`
	RequestID string `json:"request_id,omitempty"`
}

type OtaRequest struct {
	IMEI      string   `json:"imei"`
	Payload   []string `json:"payload"`
	RequestID string   `json:"request_id"`
}

type LiveDeviceResponse struct {
	Count   int              `json:"count"`
	Devices []LiveDeviceInfo `json:"devices"`
}

type LiveDeviceInfo struct {
	IMEI        string `json:"imei"`
	Remote      string `json:"remote"`
	LastSeenUTC string `json:"last_seen_utc,omitempty"`
}

func (g *Gateway) handleLiveDevicesHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	conns := g.devices.List()
	devices := make([]LiveDeviceInfo, 0, len(conns))
	for _, conn := range conns {
		if conn == nil {
			continue
		}
		imei, remote, lastSeen, closed := conn.Snapshot()
		if imei == "" || closed {
			continue
		}

		info := LiveDeviceInfo{
			IMEI:   imei,
			Remote: remote,
		}
		if !lastSeen.IsZero() {
			info.LastSeenUTC = lastSeen.UTC().Format(time.RFC3339)
		}
		devices = append(devices, info)
	}

	writeJSON(w, http.StatusOK, LiveDeviceResponse{
		Count:   len(devices),
		Devices: devices,
	})
}

type UnlockResponse struct {
	IMEI      string `json:"imei"`
	UserID    string `json:"user_id"`
	UnixTime  int64  `json:"unix_time"`
	Result    int    `json:"result"`
	DeviceTS  string `json:"device_ts,omitempty"`
	Status    string `json:"status"`
	Error     string `json:"error,omitempty"`
	CommandID string `json:"command_id,omitempty"`
	RequestID string `json:"request_id,omitempty"`
}

func (g *Gateway) handleUnlockHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req UnlockRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}

	if req.CommandID == "" && req.RequestID != "" {
		req.CommandID = req.RequestID
	}
	if req.RequestID == "" && req.CommandID != "" {
		req.RequestID = req.CommandID
	}

	log.Printf("UNLOCK REQUEST imei=%s reset_flag=%d unix_time=%d request_id=%s",
		maskIMEI(req.IMEI), req.ResetFlag.Int(), req.UnixTime, req.RequestID)

	resp, err := g.Unlock(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		switch {
		case errors.Is(err, ErrOffline):
			status = http.StatusConflict
		case errors.Is(err, ErrTimeout):
			status = http.StatusGatewayTimeout
		}
		writeJSON(w, status, resp)
		return
	}

	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleGPSOnceHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req GPSOnceRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}

	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("GPS ONCE REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)

	resp, err := g.GPSOnce(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		switch {
		case errors.Is(err, ErrOffline):
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}

	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleStatusHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req StatusRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("STATUS REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.Status(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleRestartConnectionHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req RestartConnectionRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("RESTART CONNECTION REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.RestartConnection(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleRestartAllConnectionsHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req RestartAllConnectionsRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}

	log.Printf("RESTART ALL CONNECTIONS REQUEST request_id=%s", req.RequestID)
	resp, err := g.RestartAllConnections(r.Context(), req)
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleRingHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req RingRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("RING REQUEST imei=%s ring_times=%d request_id=%s", req.IMEI, req.RingTimes.Int(), req.RequestID)
	resp, err := g.Ring(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleTrackHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req TrackRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("TRACK REQUEST imei=%s interval=%d request_id=%s", req.IMEI, req.IntervalSeconds.Int(), req.RequestID)
	resp, err := g.Track(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handlePowerOffHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req SimpleCommandRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("POWER OFF REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.PowerOff(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleRebootHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req SimpleCommandRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("REBOOT REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.Reboot(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleExternalDeviceHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req ExternalDeviceControlRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}
	if req.Operation.Int() == 0 {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "operation is required"})
		return
	}

	log.Printf("EXTERNAL DEVICE REQUEST imei=%s operation=%d request_id=%s", req.IMEI, req.Operation.Int(), req.RequestID)
	resp, err := g.ExternalDeviceControl(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleDeviceInfoHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req DeviceInfoRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("DEVICE INFO REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.DeviceInfo(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleCableLockInfoHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req SimpleCommandRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("CABLE LOCK INFO REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.CableLockInfo(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleBleKeyHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req BleKeyRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("BLE KEY REQUEST imei=%s mode=%d request_id=%s", req.IMEI, req.Mode.Int(), req.RequestID)
	resp, err := g.BleKey(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleSimInfoHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req SimInfoRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("SIM ICCID REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.SimInfo(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleBleMacHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req BleMacRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("BLE MAC REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.BleMac(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleWifiPositioningHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req WifiPositioningRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}
	if req.Mode.Int() < 0 || req.Mode.Int() > 3 {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "mode must be between 0 and 3"})
		return
	}
	if req.ScanSeconds.Int() <= 0 {
		req.ScanSeconds = IntOrNumber("3")
	}

	log.Printf("WIFI POSITIONING REQUEST imei=%s mode=%d scan_seconds=%d request_id=%s",
		req.IMEI, req.Mode.Int(), req.ScanSeconds.Int(), req.RequestID)
	resp, err := g.WifiPositioning(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleBeaconValidationHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req BeaconValidationRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}
	if req.Result.Int() < 0 || req.Result.Int() > 4 {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "result must be between 0 and 4"})
		return
	}

	log.Printf("BEACON VALIDATION REQUEST imei=%s result=%d request_id=%s", req.IMEI, req.Result.Int(), req.RequestID)
	resp, err := g.BeaconValidation(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleRFIDAdminCardHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req RFIDAdminCardRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}
	if req.Operation.Int() < 0 || req.Operation.Int() > 2 {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "operation must be between 0 and 2"})
		return
	}
	req.CardNumber = strings.TrimSpace(req.CardNumber)
	if req.Operation.Int() == 0 && req.CardNumber == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "card_number is required for set operation"})
		return
	}
	if req.CardNumber == "" {
		req.CardNumber = "0"
	}

	log.Printf("RFID ADMIN CARD REQUEST imei=%s operation=%d request_id=%s", req.IMEI, req.Operation.Int(), req.RequestID)
	resp, err := g.RFIDAdminCard(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleOtaDetectHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req OtaRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("OTA DETECT REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.OtaDetect(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleOtaStartHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req OtaRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("OTA START REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.OtaStart(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) handleOtaFinishHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	var req OtaRequest
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": err.Error()})
		return
	}
	if req.IMEI == "" {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "imei is required"})
		return
	}

	log.Printf("OTA FINISH REQUEST imei=%s request_id=%s", req.IMEI, req.RequestID)
	resp, err := g.OtaFinish(r.Context(), req)
	if err != nil {
		status := http.StatusInternalServerError
		if errors.Is(err, ErrOffline) {
			status = http.StatusConflict
		}
		writeJSON(w, status, resp)
		return
	}
	writeJSON(w, http.StatusOK, resp)
}

func (g *Gateway) Unlock(ctx context.Context, req UnlockRequest) (UnlockResponse, error) {
	resp := UnlockResponse{
		IMEI:      req.IMEI,
		UserID:    req.UserID.String(),
		UnixTime:  req.UnixTime,
		CommandID: req.CommandID,
		RequestID: req.RequestID,
	}

	if req.IMEI == "" || req.UserID.String() == "" {
		resp.Status = "invalid_request"
		resp.Error = "imei and user_id are required"
		return resp, fmt.Errorf("invalid request")
	}

	if req.UnixTime == 0 {
		req.UnixTime = time.Now().UTC().Unix()
		resp.UnixTime = req.UnixTime
	}

	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		log.Printf("UNLOCK OFFLINE imei=%s unix_time=%d request_id=%s", maskIMEI(req.IMEI), req.UnixTime, req.RequestID)
		g.emitEvent("command.unlock.offline", map[string]any{
			"imei": req.IMEI, "user_id": req.UserID.String(), "unix_time": req.UnixTime, "request_id": req.RequestID,
		})
		return resp, ErrOffline
	}

	key := inflightKey(req.IMEI, req.UserID.String(), req.UnixTime)
	ch := g.inflight.GetOrCreate(key, req.RequestID)
	defer g.inflight.Remove(key)

	var lastErr error
	for attempt := 0; attempt <= g.cfg.CommandRetries; attempt++ {
		if attempt > 0 {
			log.Printf("UNLOCK RETRY imei=%s unix_time=%d attempt=%d", maskIMEI(req.IMEI), req.UnixTime, attempt+1)
		}
		err := g.sendUnlock(conn, req)
		if err != nil {
			lastErr = err
			g.unregisterDevice(conn)
			_ = conn.Close()
			resp.Status = "offline"
			resp.Error = "write failed"
			log.Printf("UNLOCK WRITE FAILED imei=%s unix_time=%d request_id=%s err=%v",
				maskIMEI(req.IMEI), req.UnixTime, req.RequestID, err)
			g.emitEvent("command.unlock.offline", map[string]any{
				"imei": req.IMEI, "user_id": req.UserID.String(), "unix_time": req.UnixTime, "request_id": req.RequestID,
				"error": err.Error(),
			})
			return resp, ErrOffline
		}

		select {
		case <-ctx.Done():
			resp.Status = "cancelled"
			resp.Error = ctx.Err().Error()
			log.Printf("UNLOCK CANCELLED imei=%s unix_time=%d request_id=%s err=%v",
				maskIMEI(req.IMEI), req.UnixTime, req.RequestID, ctx.Err())
			return resp, ctx.Err()
		case msg := <-ch:
			resp.Result = msg.Result
			resp.DeviceTS = msg.DeviceTS
			resp.Status = "received"
			log.Printf("UNLOCK RESULT imei=%s unix_time=%d result=%d", maskIMEI(msg.IMEI), msg.UnixTime, msg.Result)
			return resp, nil
		case <-time.After(g.cfg.CommandTimeout):
			lastErr = ErrTimeout
			if attempt < g.cfg.CommandRetries {
				continue
			}
		}
	}

	resp.Status = "timeout"
	resp.Error = "no response from device"
	log.Printf("UNLOCK TIMEOUT imei=%s unix_time=%d request_id=%s timeout_ms=%d",
		maskIMEI(req.IMEI), req.UnixTime, req.RequestID, g.cfg.CommandTimeout.Milliseconds())
	g.emitEvent("command.unlock.timeout", map[string]any{
		"imei": req.IMEI, "user_id": req.UserID.String(), "unix_time": req.UnixTime, "request_id": req.RequestID,
		"timeout_ms": g.cfg.CommandTimeout.Milliseconds(),
	})
	if lastErr == nil {
		lastErr = ErrTimeout
	}
	return resp, lastErr
}

func (g *Gateway) GPSOnce(ctx context.Context, req GPSOnceRequest) (GPSOnceResponse, error) {
	resp := GPSOnceResponse{IMEI: req.IMEI, RequestID: req.RequestID}

	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.gps_once.offline", map[string]any{
			"imei":       req.IMEI,
			"request_id": req.RequestID,
		})
		return resp, ErrOffline
	}

	if err := g.sendGPSOnce(conn, req); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent("command.gps_once.offline", map[string]any{
			"imei":       req.IMEI,
			"request_id": req.RequestID,
			"error":      err.Error(),
		})
		return resp, ErrOffline
	}

	resp.Status = "sent"
	g.emitEvent("command.gps_once.sent", map[string]any{
		"imei":       req.IMEI,
		"request_id": req.RequestID,
	})
	return resp, nil
}

func (g *Gateway) Status(ctx context.Context, req StatusRequest) (CommandResponse, error) {
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.status.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
		return resp, ErrOffline
	}
	if err := g.sendStatus(conn, req); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent("command.status.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "error": err.Error()})
		return resp, ErrOffline
	}
	resp.Status = "sent"
	g.emitEvent("command.status.sent", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
	return resp, nil
}

func (g *Gateway) RestartConnection(ctx context.Context, req RestartConnectionRequest) (CommandResponse, error) {
	_ = ctx
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.restart_connection.offline", map[string]any{
			"imei":       req.IMEI,
			"request_id": req.RequestID,
		})
		return resp, ErrOffline
	}

	g.unregisterDevice(conn)
	if err := conn.Close(); err != nil {
		resp.Status = "failed"
		resp.Error = "close failed"
		g.emitEvent("command.restart_connection.failed", map[string]any{
			"imei":       req.IMEI,
			"request_id": req.RequestID,
			"error":      err.Error(),
		})
		return resp, err
	}

	resp.Status = "restarting"
	g.emitEvent("command.restart_connection.sent", map[string]any{
		"imei":       req.IMEI,
		"request_id": req.RequestID,
	})
	return resp, nil
}

func (g *Gateway) RestartAllConnections(ctx context.Context, req RestartAllConnectionsRequest) (RestartAllConnectionsResponse, error) {
	_ = ctx
	resp := RestartAllConnectionsResponse{
		Status:    "restarting",
		RequestID: req.RequestID,
	}

	conns := g.devices.List()
	for _, conn := range conns {
		g.unregisterDevice(conn)
		_ = conn.Close()
	}

	resp.ClosedConnections = len(conns)
	g.emitEvent("command.restart_all_connections.sent", map[string]any{
		"request_id":         req.RequestID,
		"closed_connections": resp.ClosedConnections,
	})
	return resp, nil
}

func (g *Gateway) Ring(ctx context.Context, req RingRequest) (CommandResponse, error) {
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.ring.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
		return resp, ErrOffline
	}
	if err := g.sendRing(conn, req); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent("command.ring.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "error": err.Error()})
		return resp, ErrOffline
	}
	resp.Status = "sent"
	g.emitEvent("command.ring.sent", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
	return resp, nil
}

func (g *Gateway) Track(ctx context.Context, req TrackRequest) (CommandResponse, error) {
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.track.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
		return resp, ErrOffline
	}
	if err := g.sendTrack(conn, req); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent("command.track.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "error": err.Error()})
		return resp, ErrOffline
	}
	resp.Status = "sent"
	g.emitEvent("command.track.sent", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "interval_seconds": req.IntervalSeconds.Int()})
	return resp, nil
}

func (g *Gateway) PowerOff(ctx context.Context, req SimpleCommandRequest) (CommandResponse, error) {
	_ = ctx
	frame := BuildPowerOffFrame(g.cfg.OM, req.IMEI, time.Now().UTC())
	return g.dispatchFrameCommand(req.IMEI, req.RequestID, "command.power_off",
		fmt.Sprintf("SEND S0 imei=%s request_id=%s", req.IMEI, req.RequestID), frame, nil)
}

func (g *Gateway) Reboot(ctx context.Context, req SimpleCommandRequest) (CommandResponse, error) {
	_ = ctx
	frame := BuildRebootFrame(g.cfg.OM, req.IMEI, time.Now().UTC())
	return g.dispatchFrameCommand(req.IMEI, req.RequestID, "command.reboot",
		fmt.Sprintf("SEND S1 imei=%s request_id=%s", req.IMEI, req.RequestID), frame, nil)
}

func (g *Gateway) ExternalDeviceControl(ctx context.Context, req ExternalDeviceControlRequest) (CommandResponse, error) {
	_ = ctx
	frame := BuildExternalDeviceControlFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), req.Operation.Int())
	return g.dispatchFrameCommand(req.IMEI, req.RequestID, "command.external_lock",
		fmt.Sprintf("SEND L5 imei=%s operation=%d request_id=%s", req.IMEI, req.Operation.Int(), req.RequestID), frame,
		map[string]any{"operation": req.Operation.Int()})
}

func (g *Gateway) DeviceInfo(ctx context.Context, req DeviceInfoRequest) (CommandResponse, error) {
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.device_info.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
		return resp, ErrOffline
	}
	if err := g.sendDeviceInfo(conn, req); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent("command.device_info.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "error": err.Error()})
		return resp, ErrOffline
	}
	resp.Status = "sent"
	g.emitEvent("command.device_info.sent", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
	return resp, nil
}

func (g *Gateway) CableLockInfo(ctx context.Context, req SimpleCommandRequest) (CommandResponse, error) {
	_ = ctx
	frame := BuildCableLockInfoFrame(g.cfg.OM, req.IMEI, time.Now().UTC())
	return g.dispatchFrameCommand(req.IMEI, req.RequestID, "command.cable_lock_info",
		fmt.Sprintf("SEND G1 imei=%s request_id=%s", req.IMEI, req.RequestID), frame, nil)
}

func (g *Gateway) BleKey(ctx context.Context, req BleKeyRequest) (CommandResponse, error) {
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.ble_key.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
		return resp, ErrOffline
	}
	if err := g.sendBleKey(conn, req); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent("command.ble_key.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "error": err.Error()})
		return resp, ErrOffline
	}
	resp.Status = "sent"
	g.emitEvent("command.ble_key.sent", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "mode": req.Mode.Int()})
	return resp, nil
}

func (g *Gateway) SimInfo(ctx context.Context, req SimInfoRequest) (CommandResponse, error) {
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.sim_iccid.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
		return resp, ErrOffline
	}
	if err := g.sendSimInfo(conn, req); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent("command.sim_iccid.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "error": err.Error()})
		return resp, ErrOffline
	}
	resp.Status = "sent"
	g.emitEvent("command.sim_iccid.sent", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
	return resp, nil
}

func (g *Gateway) BleMac(ctx context.Context, req BleMacRequest) (CommandResponse, error) {
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent("command.ble_mac.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
		return resp, ErrOffline
	}
	if err := g.sendBleMac(conn, req); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent("command.ble_mac.offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "error": err.Error()})
		return resp, ErrOffline
	}
	resp.Status = "sent"
	g.emitEvent("command.ble_mac.sent", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
	return resp, nil
}

func (g *Gateway) WifiPositioning(ctx context.Context, req WifiPositioningRequest) (CommandResponse, error) {
	_ = ctx
	frame := BuildWifiPositioningFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), req.Mode.Int(), req.ScanSeconds.Int())
	return g.dispatchFrameCommand(req.IMEI, req.RequestID, "command.wifi_positioning",
		fmt.Sprintf("SEND D2 imei=%s mode=%d scan_seconds=%d request_id=%s",
			req.IMEI, req.Mode.Int(), req.ScanSeconds.Int(), req.RequestID), frame,
		map[string]any{"mode": req.Mode.Int(), "scan_seconds": req.ScanSeconds.Int()})
}

func (g *Gateway) BeaconValidation(ctx context.Context, req BeaconValidationRequest) (CommandResponse, error) {
	_ = ctx
	frame := BuildBeaconValidationFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), req.Result.Int())
	return g.dispatchFrameCommand(req.IMEI, req.RequestID, "command.beacon_validation",
		fmt.Sprintf("SEND B0 imei=%s result=%d request_id=%s", req.IMEI, req.Result.Int(), req.RequestID), frame,
		map[string]any{"result": req.Result.Int()})
}

func (g *Gateway) RFIDAdminCard(ctx context.Context, req RFIDAdminCardRequest) (CommandResponse, error) {
	_ = ctx
	frame := BuildRFIDAdminCardFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), req.Operation.Int(), req.CardNumber)
	return g.dispatchFrameCommand(req.IMEI, req.RequestID, "command.rfid_admin_card",
		fmt.Sprintf("SEND C1 imei=%s operation=%d request_id=%s", req.IMEI, req.Operation.Int(), req.RequestID), frame,
		map[string]any{"operation": req.Operation.Int(), "card_number": req.CardNumber})
}

func (g *Gateway) OtaDetect(ctx context.Context, req OtaRequest) (CommandResponse, error) {
	return g.sendOta(ctx, req, "U0", "command.ota.detect")
}

func (g *Gateway) OtaStart(ctx context.Context, req OtaRequest) (CommandResponse, error) {
	return g.sendOta(ctx, req, "U1", "command.ota.start")
}

func (g *Gateway) OtaFinish(ctx context.Context, req OtaRequest) (CommandResponse, error) {
	return g.sendOta(ctx, req, "U2", "command.ota.finish")
}

func (g *Gateway) sendUnlock(conn *DeviceConn, req UnlockRequest) error {
	frame := BuildUnlockFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), req.ResetFlag.Int(), req.UserID.String(), req.UnixTime)
	log.Printf("SEND L0 imei=%s unix_time=%d request_id=%s", maskIMEI(req.IMEI), req.UnixTime, req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) sendGPSOnce(conn *DeviceConn, req GPSOnceRequest) error {
	frame := BuildGPSOnceFrame(g.cfg.OM, req.IMEI, time.Now().UTC())
	log.Printf("SEND D0 imei=%s request_id=%s", req.IMEI, req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) sendStatus(conn *DeviceConn, req StatusRequest) error {
	frame := BuildStatusFrame(g.cfg.OM, req.IMEI, time.Now().UTC())
	log.Printf("SEND S5 imei=%s request_id=%s", req.IMEI, req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) sendRing(conn *DeviceConn, req RingRequest) error {
	frame := BuildRingFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), req.RingTimes.Int())
	log.Printf("SEND S8 imei=%s ring_times=%d request_id=%s", req.IMEI, req.RingTimes.Int(), req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) sendTrack(conn *DeviceConn, req TrackRequest) error {
	frame := BuildTrackingFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), req.IntervalSeconds.Int())
	log.Printf("SEND D1 imei=%s interval=%d request_id=%s", req.IMEI, req.IntervalSeconds.Int(), req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) sendDeviceInfo(conn *DeviceConn, req DeviceInfoRequest) error {
	frame := BuildDeviceInfoFrame(g.cfg.OM, req.IMEI, time.Now().UTC())
	log.Printf("SEND G0 imei=%s request_id=%s", req.IMEI, req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) sendBleKey(conn *DeviceConn, req BleKeyRequest) error {
	frame := BuildBleKeyFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), req.Mode.Int(), req.Key)
	log.Printf("SEND K0 imei=%s mode=%d request_id=%s", req.IMEI, req.Mode.Int(), req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) sendSimInfo(conn *DeviceConn, req SimInfoRequest) error {
	frame := BuildSimInfoFrame(g.cfg.OM, req.IMEI, time.Now().UTC())
	log.Printf("SEND I0 imei=%s request_id=%s", req.IMEI, req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) sendBleMac(conn *DeviceConn, req BleMacRequest) error {
	frame := BuildBleMacFrame(g.cfg.OM, req.IMEI, time.Now().UTC())
	log.Printf("SEND M0 imei=%s request_id=%s", req.IMEI, req.RequestID)
	return conn.WriteFrame(frame, true, g.cfg.WriteTimeout)
}

func (g *Gateway) dispatchFrameCommand(imei, requestID, eventPrefix, logMessage, frame string, extra map[string]any) (CommandResponse, error) {
	resp := CommandResponse{IMEI: imei, RequestID: requestID}
	conn, ok := g.devices.Get(imei)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent(eventPrefix+".offline", map[string]any{"imei": imei, "request_id": requestID})
		return resp, ErrOffline
	}

	log.Printf("%s", logMessage)
	if err := conn.WriteFrame(frame, true, g.cfg.WriteTimeout); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent(eventPrefix+".offline", map[string]any{"imei": imei, "request_id": requestID, "error": err.Error()})
		return resp, ErrOffline
	}

	resp.Status = "sent"
	payload := map[string]any{"imei": imei, "request_id": requestID}
	for key, value := range extra {
		payload[key] = value
	}
	g.emitEvent(eventPrefix+".sent", payload)
	return resp, nil
}

func (g *Gateway) sendOta(ctx context.Context, req OtaRequest, cmd string, eventPrefix string) (CommandResponse, error) {
	resp := CommandResponse{IMEI: req.IMEI, RequestID: req.RequestID}
	conn, ok := g.devices.Get(req.IMEI)
	if !ok {
		resp.Status = "offline"
		resp.Error = "device not connected"
		g.emitEvent(eventPrefix+".offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
		return resp, ErrOffline
	}
	frame := BuildOtaFrame(g.cfg.OM, req.IMEI, time.Now().UTC(), cmd, req.Payload)
	log.Printf("SEND %s imei=%s request_id=%s", cmd, req.IMEI, req.RequestID)
	if err := conn.WriteFrame(frame, true, g.cfg.WriteTimeout); err != nil {
		g.unregisterDevice(conn)
		_ = conn.Close()
		resp.Status = "offline"
		resp.Error = "write failed"
		g.emitEvent(eventPrefix+".offline", map[string]any{"imei": req.IMEI, "request_id": req.RequestID, "error": err.Error()})
		return resp, ErrOffline
	}
	resp.Status = "sent"
	g.emitEvent(eventPrefix+".sent", map[string]any{"imei": req.IMEI, "request_id": req.RequestID})
	return resp, nil
}

type outboundEvent struct {
	Event      string         `json:"event"`
	EventID    string         `json:"event_id"`
	Payload    map[string]any `json:"payload"`
	OccurredAt time.Time      `json:"occurred_at"`
}

func (g *Gateway) StartEventWorkers(ctx context.Context) {
	g.eventSpool.Start(ctx)
	for i := 0; i < g.cfg.EventWorkers; i++ {
		go func(workerID int) {
			for {
				evt, ok := g.eventSpool.Next(ctx)
				if !ok {
					return
				}
				if g.deliverEvent(ctx, workerID, evt) {
					if err := g.eventSpool.Complete(evt); err != nil {
						log.Printf("event spool completion error event=%s event_id=%s err=%v", evt.Event, evt.EventID, err)
					}
				} else {
					g.eventSpool.Retry(evt)
				}
			}
		}(i + 1)
	}
}

func (g *Gateway) emitEvent(event string, payload map[string]any) {
	if g.cfg.EventWebhookURL == "" {
		log.Printf("event skipped (no webhook) event=%s", event)
		return
	}

	copiedPayload := make(map[string]any, len(payload))
	for key, value := range payload {
		copiedPayload[key] = value
	}

	occurredAt := time.Now().UTC()
	evt := outboundEvent{
		Event:      event,
		EventID:    newEventID(occurredAt, event),
		Payload:    copiedPayload,
		OccurredAt: occurredAt,
	}

	if err := g.eventSpool.Enqueue(evt); err != nil {
		log.Printf("CRITICAL event persistence failed event=%s event_id=%s err=%v", event, evt.EventID, err)
	}
}

func (g *Gateway) deliverEvent(parent context.Context, workerID int, evt outboundEvent) bool {
	body := map[string]any{
		"event_id":    evt.EventID,
		"event":       evt.Event,
		"payload":     evt.Payload,
		"occurred_at": evt.OccurredAt.Format(time.RFC3339),
	}

	data, err := json.Marshal(body)
	if err != nil {
		log.Printf("event marshal error event=%s worker=%d err=%v", evt.Event, workerID, err)
		return false
	}

	maxAttempts := g.cfg.EventRetries + 1
	if maxAttempts < 1 {
		maxAttempts = 1
	}

	for attempt := 1; attempt <= maxAttempts; attempt++ {
		ctx, cancel := context.WithTimeout(parent, g.cfg.EventHTTPTimeout)
		req, err := http.NewRequestWithContext(ctx, http.MethodPost, g.cfg.EventWebhookURL, bytes.NewReader(data))
		if err != nil {
			cancel()
			log.Printf("event request error event=%s worker=%d err=%v", evt.Event, workerID, err)
			return false
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("X-Gateway-Event-Id", evt.EventID)
		timestamp := strconv.FormatInt(time.Now().UTC().Unix(), 10)
		req.Header.Set(timestampHeader, timestamp)
		req.Header.Set(nonceHeader, evt.EventID)
		req.Header.Set(signatureHeader, signRequest(
			g.cfg.EventWebhookToken,
			timestamp,
			evt.EventID,
			http.MethodPost,
			requestTarget(req.URL),
			data,
		))

		resp, err := g.httpClient.Do(req)
		if err != nil {
			cancel()
			log.Printf("event post error event=%s event_id=%s worker=%d attempt=%d/%d err=%v",
				evt.Event, evt.EventID, workerID, attempt, maxAttempts, err)
		} else {
			_, _ = io.Copy(io.Discard, io.LimitReader(resp.Body, 4096))
			_ = resp.Body.Close()
			cancel()
			if resp.StatusCode >= 200 && resp.StatusCode < 300 {
				log.Printf("event delivered event=%s event_id=%s worker=%d status=%d attempt=%d",
					evt.Event, evt.EventID, workerID, resp.StatusCode, attempt)
				return true
			}
			log.Printf("event non-2xx event=%s event_id=%s worker=%d status=%d attempt=%d/%d",
				evt.Event, evt.EventID, workerID, resp.StatusCode, attempt, maxAttempts)
		}

		if attempt < maxAttempts {
			delay := g.cfg.EventRetryDelay
			if delay <= 0 {
				delay = time.Second
			}
			delay *= time.Duration(attempt)
			if jitterWindow := delay / 4; jitterWindow > 0 {
				delay += time.Duration(rand.Int64N(int64(jitterWindow)))
			}
			select {
			case <-parent.Done():
				return false
			case <-time.After(delay):
			}
		}
	}

	log.Printf("event delivery exhausted event=%s event_id=%s worker=%d attempts=%d", evt.Event, evt.EventID, workerID, maxAttempts)
	return false
}

func writeJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}

func getEnv(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}

func getEnvInt(key string, fallback int) int {
	if value := os.Getenv(key); value != "" {
		if parsed, err := strconv.Atoi(value); err == nil {
			return parsed
		}
	}
	return fallback
}

func getEnvDuration(key string, fallback time.Duration) time.Duration {
	if value := os.Getenv(key); value != "" {
		if parsed, err := strconv.Atoi(value); err == nil {
			return time.Duration(parsed) * time.Second
		}
	}
	return fallback
}

func getEnvBool(key string, fallback bool) bool {
	if value := strings.TrimSpace(os.Getenv(key)); value != "" {
		if parsed, err := strconv.ParseBool(value); err == nil {
			return parsed
		}
	}
	return fallback
}

func getEnvList(key string) []string {
	raw := strings.TrimSpace(os.Getenv(key))
	if raw == "" {
		return nil
	}
	parts := strings.Split(raw, ",")
	values := make([]string, 0, len(parts))
	for _, part := range parts {
		if value := strings.TrimSpace(part); value != "" {
			values = append(values, value)
		}
	}
	return values
}

// StringOrNumber allows user_id to be either a JSON string or number.
type StringOrNumber string

func (s *StringOrNumber) UnmarshalJSON(b []byte) error {
	b = bytes.TrimSpace(b)
	if len(b) == 0 {
		return nil
	}
	if b[0] == '"' {
		var v string
		if err := json.Unmarshal(b, &v); err != nil {
			return err
		}
		*s = StringOrNumber(v)
		return nil
	}
	*s = StringOrNumber(string(b))
	return nil
}

func (s StringOrNumber) String() string {
	return string(s)
}

// IntOrNumber allows numeric fields to be JSON numbers or strings.
type IntOrNumber string

func (i *IntOrNumber) UnmarshalJSON(b []byte) error {
	b = bytes.TrimSpace(b)
	if len(b) == 0 {
		return nil
	}
	if b[0] == '"' {
		var v string
		if err := json.Unmarshal(b, &v); err != nil {
			return err
		}
		*i = IntOrNumber(v)
		return nil
	}
	*i = IntOrNumber(string(b))
	return nil
}

func (i IntOrNumber) Int() int {
	if i == "" {
		return 0
	}
	if parsed, err := strconv.Atoi(string(i)); err == nil {
		return parsed
	}
	return 0
}
