package main

import (
	"errors"
	"log"
	"net"
	"time"
)

var (
	errInvalidDeviceIdentity = errors.New("invalid device identity")
	errDeviceIMEIMismatch    = errors.New("connection IMEI mismatch")
)

func (g *Gateway) registerDevice(imei string, conn *DeviceConn) {
	if imei == "" || conn == nil {
		return
	}
	conn.SetIMEI(imei)
	g.devices.Set(imei, conn)
	log.Printf("DEVICE REGISTERED imei=%s remote=%s", maskIMEI(imei), conn.remote)
	ip := remoteIP(conn.remote)
	log.Printf("EVENT device.online emit source=connect imei=%s", maskIMEI(imei))
	g.emitEvent("device.online", map[string]any{
		"imei":         imei,
		"ip":           ip,
		"connected_at": time.Now().UTC().Format(time.RFC3339),
	})
}

func (g *Gateway) unregisterDevice(conn *DeviceConn) {
	if conn == nil {
		return
	}
	imei, remote, _, _ := conn.Snapshot()
	if imei == "" {
		return
	}
	g.devices.Remove(imei, conn)
	log.Printf("DEVICE UNREGISTERED imei=%s remote=%s", maskIMEI(imei), remote)
}

func (g *Gateway) deviceIMEIAllowed(imei string) bool {
	if len(g.allowedIMEIs) == 0 {
		return g.cfg.Environment != "production"
	}
	_, allowed := g.allowedIMEIs[imei]
	return allowed
}

func (g *Gateway) bindDeviceMessage(conn *DeviceConn, message Message) error {
	if conn == nil || message.Om != g.cfg.OM || !validIMEI(message.IMEI) || !g.deviceIMEIAllowed(message.IMEI) {
		return errInvalidDeviceIdentity
	}
	registered := conn.IMEI()
	if registered != "" && registered != message.IMEI {
		return errDeviceIMEIMismatch
	}
	if registered == "" {
		g.registerDevice(message.IMEI, conn)
	}
	return nil
}

func (g *Gateway) deviceIPAllowed(rawIP string) bool {
	if len(g.allowedDeviceNets) == 0 {
		return true
	}
	ip := net.ParseIP(rawIP)
	if ip == nil {
		return false
	}
	for _, network := range g.allowedDeviceNets {
		if network.Contains(ip) {
			return true
		}
	}
	return false
}

func isTimeout(err error) bool {
	if err == nil {
		return false
	}
	if netErr, ok := err.(net.Error); ok && netErr.Timeout() {
		return true
	}
	return false
}

func remoteIP(addr string) string {
	if addr == "" {
		return ""
	}
	host, _, err := net.SplitHostPort(addr)
	if err == nil {
		return host
	}
	return addr
}
