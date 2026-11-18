@extends('layouts.marketing')

@section('title', 'Why Choose Dockless Bike Sharing | ' . config('app.name', 'BISKLET'))
@section('meta_description', 'Learn why ' . config('app.name', 'BISKLET') . ' is built for safe, reliable dockless bike sharing with responsible parking, rider support, and cleaner city transport.')
@section('meta_keywords', 'why bike sharing, dockless mobility, sustainable transport, smart bike parking, city bike sharing benefits')

@section('content')
    <section class="hero">
        <div class="hero-copy">
            <div class="eyebrow reveal"><span></span>Why {{ config('app.name', 'BISKLET') }}</div>
            <h1 class="reveal delay-1">Mobility that feels lighter for riders and cities.</h1>
            <p class="lead reveal delay-2">We build shared bikes that start fast, stop smoothly, and keep streets tidy. From smart parking to responsive support, every decision is tuned to keep riders safe and cities calm.</p>
            <div class="hero-actions reveal delay-3">
                <a class="btn primary" href="{{ route('vehicles') }}">Explore vehicles</a>
                <a class="btn secondary" href="{{ route('home') }}#download">Start riding</a>
            </div>
            <div class="hero-stats reveal delay-4">
                <div class="stat">
                    <strong>4.8/5</strong>
                    <span>Rider rating</span>
                </div>
                <div class="stat">
                    <strong>2 min</strong>
                    <span>Average unlock</span>
                </div>
                <div class="stat">
                    <strong>24/7</strong>
                    <span>Support team</span>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="ride-card reveal delay-2">
                <h3>Trust &amp; safety layers</h3>
                <p>We pair hardware, software, and human support for safer rides.</p>
                <div class="map">
                    <span class="active"></span>
                    <span></span>
                    <span></span>
                    <span class="active"></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span class="active"></span>
                    <span></span>
                    <span></span>
                    <span class="active"></span>
                    <span></span>
                </div>
                <div class="pulse">
                    <span class="pulse-dot"></span>
                    Night lighting active
                </div>
            </div>
        </div>
    </section>

    <div class="sections">
        <section>
            <h2 class="section-title">Built for real streets.</h2>
            <p class="section-sub">Every ride is a mix of busy corners, rainy mornings, and short errands. {{ config('app.name', 'BISKLET') }} blends comfort and control so you feel confident from curb to curb.</p>
            <div class="features">
                <div class="feature reveal">
                    <h4>Predictable handling</h4>
                    <p>Balanced geometry and wider tires keep rides steady on uneven roads.</p>
                </div>
                <div class="feature reveal delay-1">
                    <h4>Weather-ready finish</h4>
                    <p>Durable, easy-clean materials that stay bright and smooth year-round.</p>
                </div>
                <div class="feature reveal delay-2">
                    <h4>Smart parking nudges</h4>
                    <p>App guidance and incentives keep sidewalks clear and rides organized.</p>
                </div>
                <div class="feature reveal delay-3">
                    <h4>Instant help</h4>
                    <p>Live chat and roadside support get you moving again quickly.</p>
                </div>
            </div>
        </section>

        <section>
            <h2 class="section-title">Safety is the default.</h2>
            <p class="section-sub">We design for visibility, stability, and clear guidance so riders can focus on the ride, not the risk.</p>
            <div class="steps">
                <div class="step-card reveal">
                    <div class="step-icon">01</div>
                    <h3>Ride-ready checks</h3>
                    <p>Routine inspections, daily field sweeps, and in-app reporting.</p>
                </div>
                <div class="step-card reveal delay-1">
                    <div class="step-icon">02</div>
                    <h3>Street-aware tech</h3>
                    <p>Adaptive lighting and braking respond to traffic and hills.</p>
                </div>
                <div class="step-card reveal delay-2">
                    <div class="step-icon">03</div>
                    <h3>Clear zones</h3>
                    <p>Mapped parking and incentive nudges reduce sidewalk clutter.</p>
                </div>
            </div>
        </section>

        <section>
            <h2 class="section-title">Impact you can measure.</h2>
            <p class="section-sub">Cleaner air, calmer streets, and shared options that reduce car trips. It is a city win.</p>
            <div class="pricing">
                <div class="price-card reveal">
                    <h3>CO2 saved</h3>
                    <div class="price">1.3M <small>lbs yearly</small></div>
                    <p>Rides that replace short car trips across the city.</p>
                </div>
                <div class="price-card highlight reveal delay-1">
                    <h3>Street noise</h3>
                    <div class="price">-22% <small>in pilot zones</small></div>
                    <p>Quieter blocks with fewer short-distance drive-offs.</p>
                </div>
                <div class="price-card reveal delay-2">
                    <h3>Local jobs</h3>
                    <div class="price">120 <small>technicians</small></div>
                    <p>Operations teams keeping bikes safe and neighborhoods tidy.</p>
                </div>
            </div>
        </section>

        <section class="coverage">
            <div>
                <h2 class="section-title">Partnered with the people who ride.</h2>
                <p class="section-sub">We build programs with cities, campuses, and local groups to keep access equitable and parking respectful.</p>
                <div class="hero-actions">
                    <a class="btn secondary" href="{{ route('home') }}#partner">See partner program</a>
                    <a class="btn primary" href="{{ route('home') }}#download">Download the app</a>
                </div>
            </div>
            <div class="map-board">
                <span class="map-dot one"></span>
                <span class="map-dot two"></span>
                <span class="map-dot three"></span>
            </div>
        </section>

        <section id="download" class="cta">
            <div>
                <h2>Try a ride today.</h2>
                <p>Unlock your first {{ config('app.name', 'BISKLET') }} bike in minutes and see how smooth city travel can be.</p>
            </div>
            <div class="hero-actions">
                <a class="btn primary" href="{{ route('home') }}#download">Get the app</a>
                <a class="btn ghost" href="{{ route('vehicles') }}">See vehicles</a>
            </div>
        </section>
    </div>
@endsection
