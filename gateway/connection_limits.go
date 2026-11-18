package main

import "sync"

type ConnectionLimiter struct {
	mu       sync.Mutex
	total    int
	byIP     map[string]int
	maxTotal int
	maxPerIP int
}

func NewConnectionLimiter(maxTotal, maxPerIP int) *ConnectionLimiter {
	if maxTotal < 1 {
		maxTotal = 1
	}
	if maxPerIP < 1 {
		maxPerIP = 1
	}
	return &ConnectionLimiter{
		byIP:     make(map[string]int),
		maxTotal: maxTotal,
		maxPerIP: maxPerIP,
	}
}

func (limiter *ConnectionLimiter) Acquire(ip string) bool {
	limiter.mu.Lock()
	defer limiter.mu.Unlock()
	if limiter.total >= limiter.maxTotal || limiter.byIP[ip] >= limiter.maxPerIP {
		return false
	}
	limiter.total++
	limiter.byIP[ip]++
	return true
}

func (limiter *ConnectionLimiter) Release(ip string) {
	limiter.mu.Lock()
	defer limiter.mu.Unlock()
	if limiter.total > 0 {
		limiter.total--
	}
	if limiter.byIP[ip] <= 1 {
		delete(limiter.byIP, ip)
	} else {
		limiter.byIP[ip]--
	}
}

func (limiter *ConnectionLimiter) Count() int {
	limiter.mu.Lock()
	defer limiter.mu.Unlock()
	return limiter.total
}
