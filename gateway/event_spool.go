package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

var eventIDPattern = regexp.MustCompile(`^[A-Za-z0-9._-]{1,128}$`)

type EventSpool struct {
	dir      string
	ready    chan outboundEvent
	wake     chan struct{}
	mu       sync.Mutex
	diskMu   sync.Mutex
	inflight map[string]struct{}
	count    atomic.Int64
	maxFiles int64
}

func NewEventSpool(dir string, queueSize, maxFiles int) (*EventSpool, error) {
	if queueSize < 1 {
		queueSize = 1
	}
	spool := &EventSpool{
		dir:      strings.TrimSpace(dir),
		ready:    make(chan outboundEvent, queueSize),
		wake:     make(chan struct{}, 1),
		inflight: make(map[string]struct{}),
		maxFiles: int64(maxFiles),
	}
	if spool.maxFiles < 1 {
		spool.maxFiles = 100000
	}
	if spool.dir != "" {
		if err := os.MkdirAll(spool.dir, 0o700); err != nil {
			return nil, fmt.Errorf("create event spool: %w", err)
		}
		entries, err := os.ReadDir(spool.dir)
		if err != nil {
			return nil, fmt.Errorf("read event spool: %w", err)
		}
		for _, entry := range entries {
			if !entry.IsDir() && strings.HasSuffix(entry.Name(), ".json") {
				spool.count.Add(1)
			}
		}
	}
	return spool, nil
}

func (spool *EventSpool) Start(ctx context.Context) {
	spool.notify()
	go func() {
		ticker := time.NewTicker(time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				spool.recoverPending()
			case <-spool.wake:
				spool.recoverPending()
			}
		}
	}()
}

func (spool *EventSpool) Enqueue(event outboundEvent) error {
	if !eventIDPattern.MatchString(event.EventID) {
		return errors.New("invalid event ID")
	}
	if spool.dir != "" {
		if err := spool.persist(event); err != nil {
			return err
		}
	}
	if !spool.tryQueue(event) && spool.dir == "" {
		return errors.New("in-memory event queue is full")
	}
	spool.notify()
	return nil
}

func (spool *EventSpool) Next(ctx context.Context) (outboundEvent, bool) {
	select {
	case <-ctx.Done():
		return outboundEvent{}, false
	case event := <-spool.ready:
		return event, true
	}
}

func (spool *EventSpool) Complete(event outboundEvent) error {
	defer spool.release(event.EventID)
	if spool.dir == "" {
		return nil
	}
	spool.diskMu.Lock()
	defer spool.diskMu.Unlock()
	err := os.Remove(spool.path(event.EventID))
	if errors.Is(err, os.ErrNotExist) {
		return nil
	}
	if err == nil {
		spool.count.Add(-1)
	}
	return err
}

func (spool *EventSpool) Retry(event outboundEvent) {
	spool.release(event.EventID)
	spool.notify()
}

func (spool *EventSpool) Pending() int {
	if spool.dir == "" {
		return len(spool.ready)
	}
	return int(spool.count.Load())
}

func (spool *EventSpool) persist(event outboundEvent) error {
	spool.diskMu.Lock()
	defer spool.diskMu.Unlock()
	if spool.count.Load() >= spool.maxFiles {
		return fmt.Errorf("event spool reached its %d-file safety limit", spool.maxFiles)
	}
	data, err := json.Marshal(event)
	if err != nil {
		return fmt.Errorf("marshal event: %w", err)
	}
	temporary, err := os.CreateTemp(spool.dir, ".event-*.tmp")
	if err != nil {
		return fmt.Errorf("create event spool file: %w", err)
	}
	temporaryName := temporary.Name()
	defer func() { _ = os.Remove(temporaryName) }()
	if err := temporary.Chmod(0o600); err != nil {
		_ = temporary.Close()
		return err
	}
	if _, err := temporary.Write(data); err != nil {
		_ = temporary.Close()
		return fmt.Errorf("write event spool file: %w", err)
	}
	if err := temporary.Sync(); err != nil {
		_ = temporary.Close()
		return fmt.Errorf("sync event spool file: %w", err)
	}
	if err := temporary.Close(); err != nil {
		return err
	}
	if err := os.Rename(temporaryName, spool.path(event.EventID)); err != nil {
		return fmt.Errorf("commit event spool file: %w", err)
	}
	spool.count.Add(1)
	return nil
}

func (spool *EventSpool) recoverPending() {
	if spool.dir == "" {
		return
	}
	entries, err := os.ReadDir(spool.dir)
	if err != nil {
		return
	}
	sort.Slice(entries, func(i, j int) bool { return entries[i].Name() < entries[j].Name() })
	for _, entry := range entries {
		if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".json") {
			continue
		}
		data, err := os.ReadFile(filepath.Join(spool.dir, entry.Name()))
		if err != nil {
			continue
		}
		var event outboundEvent
		if err := json.Unmarshal(data, &event); err != nil || !eventIDPattern.MatchString(event.EventID) {
			continue
		}
		if !spool.tryQueue(event) {
			return
		}
	}
}

func (spool *EventSpool) tryQueue(event outboundEvent) bool {
	spool.mu.Lock()
	if _, exists := spool.inflight[event.EventID]; exists {
		spool.mu.Unlock()
		return true
	}
	spool.inflight[event.EventID] = struct{}{}
	spool.mu.Unlock()
	select {
	case spool.ready <- event:
		return true
	default:
		spool.release(event.EventID)
		return false
	}
}

func (spool *EventSpool) release(eventID string) {
	spool.mu.Lock()
	delete(spool.inflight, eventID)
	spool.mu.Unlock()
}

func (spool *EventSpool) notify() {
	select {
	case spool.wake <- struct{}{}:
	default:
	}
}

func (spool *EventSpool) path(eventID string) string {
	return filepath.Join(spool.dir, eventID+".json")
}
