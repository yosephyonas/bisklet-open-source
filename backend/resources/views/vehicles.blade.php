@extends('layouts.marketing')

@section('title', 'Shared Bikes & Ebike Fleet | ' . config('app.name', 'BISKLET'))
@section('meta_description', 'Explore the ' . config('app.name', 'BISKLET') . ' fleet: shared bikes and ebikes built for dockless bike sharing, daily commutes, and city mobility.')
@section('meta_keywords', 'shared bikes, ebike fleet, dockless bikes, bike sharing vehicles, urban mobility bikes, bike rental fleet')

@section('content')
    <section class="hero">
        <div class="hero-copy">
            <div class="eyebrow reveal"><span></span>{{ config('app.name', 'BISKLET') }} vehicles</div>
            <h1 class="reveal delay-1">Built for city flow, tuned for real riders.</h1>
            <p class="lead reveal delay-2">Our fleet blends stability, comfort, and speed. Each bike is built with rugged materials, bright lighting, and intuitive controls for smooth starts and safe stops.</p>
            <div class="hero-actions reveal delay-3">
                <a class="btn primary" href="{{ route('home') }}#download">Ride now</a>
                <a class="btn secondary" href="{{ route('why') }}">Why BISKLET</a>
            </div>
            <div class="hero-stats reveal delay-4">
                <div class="stat">
                    <strong>35 mi</strong>
                    <span>Max range</span>
                </div>
                <div class="stat">
                    <strong>9 sec</strong>
                    <span>Quick unlock</span>
                </div>
                <div class="stat">
                    <strong>3 modes</strong>
                    <span>Assist levels</span>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="ride-card reveal delay-2">
                <h3>Battery + range</h3>
                <p>Live monitoring keeps bikes charged and ready.</p>
                <div class="map">
                    <span class="active"></span>
                    <span class="active"></span>
                    <span class="active"></span>
                    <span class="active"></span>
                    <span></span>
                    <span></span>
                    <span class="active"></span>
                    <span class="active"></span>
                    <span></span>
                    <span></span>
                    <span class="active"></span>
                    <span></span>
                </div>
                <div class="pulse">
                    <span class="pulse-dot"></span>
                    Average charge at 82%
                </div>
            </div>
        </div>
    </section>

    <div class="sections">
        <section id="fleet">
            <h2 class="section-title">Meet the fleet.</h2>
            <p class="section-sub">Pick a ride that matches your pace, from light manual bikes to power assist cruisers.</p>
            <div class="fleet">
                <div class="fleet-card reveal">
                    <span>City core</span>
                    <h3>LiteRide S</h3>
                    <p>Lightweight and nimble for short trips and quick turns.</p>
                </div>
                <div class="fleet-card reveal delay-1">
                    <span>Comfort</span>
                    <h3>Glide+</h3>
                    <p>Extra cushion, relaxed posture, and a smooth roll.</p>
                </div>
                <div class="fleet-card reveal delay-2">
                    <span>Power assist</span>
                    <h3>Boost E</h3>
                    <p>Quiet electric assist for hills and longer stretches.</p>
                </div>
            </div>
        </section>

        <section>
            <h2 class="section-title">Designed to last through every season.</h2>
            <p class="section-sub">We build for weather, wear, and the daily demands of shared use.</p>
            <div class="features">
                <div class="feature reveal">
                    <h4>Weather-resistant build</h4>
                    <p>Sealed components and durable coatings keep rides smooth.</p>
                </div>
                <div class="feature reveal delay-1">
                    <h4>Bright visibility</h4>
                    <p>Integrated lighting and reflective details keep riders visible.</p>
                </div>
                <div class="feature reveal delay-2">
                    <h4>Secure locking</h4>
                    <p>Auto-lock tech ensures bikes stay put when the ride ends.</p>
                </div>
                <div class="feature reveal delay-3">
                    <h4>Comfort-first fit</h4>
                    <p>Adjustable seats and ergonomic grips for every rider.</p>
                </div>
            </div>
        </section>

        <section>
            <h2 class="section-title">Specs that make a difference.</h2>
            <p class="section-sub">Built for consistency with the range, power, and durability to keep you moving.</p>
            <div class="pricing">
                <div class="price-card reveal">
                    <h3>LiteRide S</h3>
                    <div class="price">18 mi <small>range</small></div>
                    <p>Lightweight frame with responsive braking and steady handling.</p>
                </div>
                <div class="price-card highlight reveal delay-1">
                    <h3>Boost E</h3>
                    <div class="price">35 mi <small>range</small></div>
                    <p>Electric assist with hill-ready torque and smooth acceleration.</p>
                </div>
                <div class="price-card reveal delay-2">
                    <h3>Glide+</h3>
                    <div class="price">24 mi <small>range</small></div>
                    <p>Comfort-tuned geometry for longer routes and easy cruising.</p>
                </div>
            </div>
        </section>

        <section>
            <h2 class="section-title">Maintenance you can count on.</h2>
            <p class="section-sub">Our field teams check bikes daily, keep fleets charged, and respond fast to rider reports.</p>
            <div class="steps">
                <div class="step-card reveal">
                    <div class="step-icon">01</div>
                    <h3>Daily inspections</h3>
                    <p>Brake checks, tire pressure, and safety tests every morning.</p>
                </div>
                <div class="step-card reveal delay-1">
                    <div class="step-icon">02</div>
                    <h3>Rapid fixes</h3>
                    <p>On-street repair teams handle most issues in under an hour.</p>
                </div>
                <div class="step-card reveal delay-2">
                    <div class="step-icon">03</div>
                    <h3>Battery swaps</h3>
                    <p>Fresh batteries keep the fleet rolling across peak hours.</p>
                </div>
            </div>
        </section>

        <section id="download" class="cta">
            <div>
                <h2>Choose your ride and go.</h2>
                <p>Open the app, pick the bike that fits your day, and roll out in minutes.</p>
            </div>
            <div class="hero-actions">
                <a class="btn primary" href="{{ route('home') }}#download">Get the app</a>
                <a class="btn ghost" href="{{ route('home') }}#pricing">See pricing</a>
            </div>
        </section>
    </div>
@endsection
