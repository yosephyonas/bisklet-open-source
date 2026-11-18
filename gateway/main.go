package main

import (
	"context"
	"errors"
	"log"
	"net"
	"os/signal"
	"syscall"
	"time"
)

func main() {
	log.SetFlags(log.LstdFlags | log.Lmicroseconds)
	cfg := loadConfig()
	if err := cfg.Validate(); err != nil {
		log.Fatalf("invalid gateway configuration: %v", err)
	}
	gateway, err := NewGateway(cfg)
	if err != nil {
		log.Fatalf("gateway initialization failed: %v", err)
	}
	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()
	gateway.StartEventWorkers(ctx)

	errCh := make(chan error, 2)

	if cfg.HTTPAddr != "" {
		go func() {
			errCh <- gateway.StartHTTP()
		}()
	}

	go func() {
		errCh <- gateway.StartTCP(ctx)
	}()

	select {
	case <-ctx.Done():
		log.Printf("shutdown signal received")
	case err := <-errCh:
		if err != nil && !errors.Is(err, ErrServerClosed) {
			log.Printf("gateway server error: %v", err)
		}
		stop()
	}

	shutdownCtx, cancel := context.WithTimeout(context.Background(), cfg.ShutdownTimeout)
	defer cancel()
	if err := gateway.Shutdown(shutdownCtx); err != nil {
		log.Printf("graceful shutdown incomplete: %v", err)
	}
}

func (g *Gateway) StartTCP(ctx context.Context) error {
	ln, err := net.Listen("tcp", g.cfg.TCPAddr)
	if err != nil {
		return err
	}
	g.serverMu.Lock()
	g.tcpListener = ln
	g.serverMu.Unlock()
	log.Printf("TCP gateway listening on %s", g.cfg.TCPAddr)
	go func() {
		<-ctx.Done()
		_ = ln.Close()
	}()

	for {
		conn, err := ln.Accept()
		if err != nil {
			if ctx.Err() != nil || errors.Is(err, net.ErrClosed) {
				return ErrServerClosed
			}
			log.Printf("accept error: %v", err)
			continue
		}
		ip := remoteIP(conn.RemoteAddr().String())
		if !g.deviceIPAllowed(ip) || !g.connections.Acquire(ip) {
			log.Printf("connection rejected remote=%s", ip)
			_ = conn.Close()
			continue
		}
		if tcpConn, ok := conn.(*net.TCPConn); ok {
			_ = tcpConn.SetKeepAlive(true)
			_ = tcpConn.SetKeepAlivePeriod(60 * time.Second)
		}
		g.connWG.Add(1)
		go func() {
			defer g.connWG.Done()
			defer g.connections.Release(ip)
			g.handleConn(conn)
		}()
	}
}

func (g *Gateway) handleConn(conn net.Conn) {
	dc := NewDeviceConn(conn)
	remote := conn.RemoteAddr().String()
	log.Printf("CONNECTED: %s", remote)
	defer func() {
		g.unregisterDevice(dc)
		_ = dc.Close()
		log.Printf("DISCONNECTED: %s", remote)
	}()

	reader := NewFrameReader(conn, g.cfg.MaxFrameBytes)
	idleTimeouts := 0

	for {
		if g.cfg.ReadTimeout > 0 {
			_ = conn.SetReadDeadline(time.Now().Add(g.cfg.ReadTimeout))
		}

		raw, err := reader.ReadFrame('#')
		if err != nil {
			if isTimeout(err) {
				idleTimeouts++
				if idleTimeouts < g.cfg.MaxIdleTimeouts {
					continue
				}
			}
			log.Printf("READ ERROR from %s: %v", remote, err)
			return
		}
		idleTimeouts = 0

		if g.cfg.DebugFrames {
			log.Printf("RAW from %s: %s", remote, truncate(raw, 512))
		}

		msg, err := ParseCMDR(raw)
		if err != nil {
			log.Printf("PARSE FAILED remote=%s err=%v", remote, err)
			continue
		}

		if err := g.bindDeviceMessage(dc, msg); err != nil {
			log.Printf("FRAME REJECTED remote=%s reason=%v", remote, err)
			return
		}
		dc.Touch()

		log.Printf("RECV imei=%s cmd=%s", maskIMEI(msg.IMEI), msg.Command)
		g.handleMessage(dc, msg)
	}
}

func truncate(value string, max int) string {
	if max <= 0 || len(value) <= max {
		return value
	}
	return value[:max] + "...(truncated)"
}
