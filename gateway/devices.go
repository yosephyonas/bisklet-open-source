package main

import (
	"log"
	"net"
	"sync"
	"sync/atomic"
	"time"
)

type DeviceConn struct {
	conn      net.Conn
	remote    string
	imei      string
	lastSeen  time.Time
	mu        sync.Mutex
	closed    atomic.Bool
	closeOnce sync.Once
}

func NewDeviceConn(conn net.Conn) *DeviceConn {
	return &DeviceConn{
		conn:   conn,
		remote: conn.RemoteAddr().String(),
	}
}

func (dc *DeviceConn) WriteFrame(frame string, withPrefix bool, timeout time.Duration) error {
	dc.mu.Lock()
	defer dc.mu.Unlock()

	if dc.closed.Load() {
		return net.ErrClosed
	}

	if timeout > 0 {
		_ = dc.conn.SetWriteDeadline(time.Now().Add(timeout))
	}

	payload := []byte(frame)
	if withPrefix {
		payload = append([]byte{0xFF, 0xFF}, payload...)
		log.Printf("WRITE prefix=FF FF remote=%s imei=%s bytes=%d", dc.remote, dc.imei, len(payload))
	}

	_, err := dc.conn.Write(payload)
	return err
}

func (dc *DeviceConn) Close() error {
	var err error
	dc.closeOnce.Do(func() {
		dc.closed.Store(true)
		err = dc.conn.Close()
	})
	return err
}

func (dc *DeviceConn) Touch() {
	dc.mu.Lock()
	defer dc.mu.Unlock()
	dc.lastSeen = time.Now().UTC()
}

func (dc *DeviceConn) SetIMEI(imei string) {
	dc.mu.Lock()
	defer dc.mu.Unlock()
	dc.imei = imei
}

func (dc *DeviceConn) IMEI() string {
	dc.mu.Lock()
	defer dc.mu.Unlock()
	return dc.imei
}

func (dc *DeviceConn) Snapshot() (imei, remote string, lastSeen time.Time, closed bool) {
	dc.mu.Lock()
	defer dc.mu.Unlock()
	return dc.imei, dc.remote, dc.lastSeen, dc.closed.Load()
}

type DeviceRegistry struct {
	mu    sync.RWMutex
	conns map[string]*DeviceConn
}

func NewDeviceRegistry() *DeviceRegistry {
	return &DeviceRegistry{conns: make(map[string]*DeviceConn)}
}

func (r *DeviceRegistry) Get(imei string) (*DeviceConn, bool) {
	r.mu.RLock()
	defer r.mu.RUnlock()
	conn, ok := r.conns[imei]
	return conn, ok
}

func (r *DeviceRegistry) Set(imei string, conn *DeviceConn) {
	if imei == "" || conn == nil {
		return
	}

	r.mu.Lock()
	previous := r.conns[imei]
	r.conns[imei] = conn
	r.mu.Unlock()

	if previous != nil && previous != conn {
		_ = previous.Close()
	}
}

func (r *DeviceRegistry) Remove(imei string, conn *DeviceConn) {
	r.mu.Lock()
	current, ok := r.conns[imei]
	if ok && current == conn {
		delete(r.conns, imei)
	}
	r.mu.Unlock()
}

func (r *DeviceRegistry) List() []*DeviceConn {
	r.mu.RLock()
	defer r.mu.RUnlock()

	all := make([]*DeviceConn, 0, len(r.conns))
	for _, conn := range r.conns {
		if conn != nil {
			all = append(all, conn)
		}
	}
	return all
}
