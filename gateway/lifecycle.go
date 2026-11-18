package main

import (
	"context"
	"errors"
	"net"
)

func (g *Gateway) Shutdown(ctx context.Context) error {
	g.serverMu.Lock()
	listener := g.tcpListener
	server := g.httpServer
	g.serverMu.Unlock()

	if listener != nil {
		_ = listener.Close()
	}
	for _, connection := range g.devices.List() {
		_ = connection.Close()
	}

	var shutdownErr error
	if server != nil {
		if err := server.Shutdown(ctx); err != nil && !errors.Is(err, net.ErrClosed) {
			shutdownErr = err
		}
	}

	done := make(chan struct{})
	go func() {
		g.connWG.Wait()
		close(done)
	}()
	select {
	case <-done:
		return shutdownErr
	case <-ctx.Done():
		if shutdownErr != nil {
			return shutdownErr
		}
		return ctx.Err()
	}
}
