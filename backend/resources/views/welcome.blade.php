@extends('layouts.marketing')

@section('title', 'Bike Sharing & Dockless Bikes in Addis Ababa | ' . config('app.name', 'BISKLET'))
@section('meta_description', config('app.name', 'BISKLET') . ' is a dockless bike sharing platform in Addis Ababa. Find nearby bikes, unlock in seconds, ride anywhere, and park responsibly.')
@section('meta_keywords', 'bike sharing, dockless bike sharing, bicycle sharing app, bike rental Addis Ababa, ebike sharing, shared bikes, micromobility Ethiopia')
@section('structured_data')
    @php
        $serviceSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => config('app.name', 'BISKLET') . ' Dockless Bike Sharing',
            'serviceType' => 'Bike sharing',
            'provider' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'BISKLET'),
                'url' => url('/'),
            ],
            'areaServed' => [
                '@type' => 'City',
                'name' => 'Addis Ababa',
            ],
            'description' => 'Dockless bike sharing service with app-based unlock and responsible parking.',
            'url' => url()->current(),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($serviceSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('content')
    <section class="hero">
        <div class="hero-copy">
            @php($heroHighlight = __('marketing.home.hero_highlight'))
            <h1 class="motion-heading hero-title">
                <span class="hero-title__line">{{ __('marketing.home.hero_title_prefix') }}</span>
                <span class="hero-title__line hero-highlight kinetic-type" aria-label="{{ $heroHighlight }}">@foreach (preg_split('/\s+/u', trim($heroHighlight), -1, PREG_SPLIT_NO_EMPTY) as $word)<span class="kinetic-type__word" style="--word-index: {{ $loop->index }}" aria-hidden="true">{{ $word }}</span>@endforeach</span>
            </h1>
            <p class="lead reveal delay-2">{{ __('marketing.home.hero_lead') }}</p>
            <div class="hero-actions reveal delay-3">
                <a class="btn primary" href="#download">{{ __('marketing.home.cta_start') }}</a>
                <a class="btn secondary" href="#how">{{ __('marketing.home.cta_how') }}</a>
            </div>
        </div>
        <div class="hero-visual">
            <figure class="hero-photo reveal delay-2">
                <div role="img" aria-label="{{ __('marketing.home.photo_bole_alt') }}" style="min-height:220px;width:100%;display:grid;place-items:center;border-radius:16px;background:linear-gradient(130deg,#e6f0ed,#bad9d0);color:#205847;font-weight:750;letter-spacing:.15em">BISKLET</div>
            </figure>
        </div>
    </section>

    <div class="sections home-sections">
        <section id="addis" class="city-story reveal">
            <div class="city-story__photo">
                <div role="img" aria-label="{{ __('marketing.home.photo_park_alt') }}" style="min-height:220px;width:100%;display:grid;place-items:center;border-radius:16px;background:linear-gradient(130deg,#e6f0ed,#bad9d0);color:#205847;font-weight:750;letter-spacing:.15em">BISKLET</div>
            </div>
            <div class="city-story__copy">
                <h2 class="section-title motion-heading"><span>{{ __('marketing.home.city_title') }}</span></h2>
                <p class="section-sub">{{ __('marketing.home.city_sub') }}</p>
            </div>
        </section>

        <section id="places" class="places-section">
            <div class="places-heading reveal">
                <h2 class="section-title motion-heading"><span>{{ __('marketing.home.places_title') }}</span></h2>
                <p class="section-sub">{{ __('marketing.home.places_sub') }}</p>
            </div>
            <div class="place-grid">
                <article class="place-card place-card--photo reveal">
                    <div role="img" aria-label="{{ __('marketing.home.university_alt') }}" style="min-height:220px;width:100%;display:grid;place-items:center;border-radius:16px;background:linear-gradient(130deg,#e6f0ed,#bad9d0);color:#205847;font-weight:750;letter-spacing:.15em">BISKLET</div>
                    <div class="place-card__shade"></div>
                    <div class="place-card__content">
                        <h3>{{ __('marketing.home.university_title') }}</h3>
                        <p>{{ __('marketing.home.university_desc') }}</p>
                    </div>
                </article>
            </div>
        </section>

        <section id="bike-lanes" class="bike-lanes reveal">
            <div class="bike-lanes__visual">
                <div role="img" aria-label="{{ __('marketing.home.bike_lanes_alt') }}" style="min-height:220px;width:100%;display:grid;place-items:center;border-radius:16px;background:linear-gradient(130deg,#e6f0ed,#bad9d0);color:#205847;font-weight:750;letter-spacing:.15em">BISKLET</div>
            </div>
            <div class="bike-lanes__copy">
                <h2 class="section-title motion-heading"><span>{{ __('marketing.home.bike_lanes_title') }}</span></h2>
                <p class="section-sub">{{ __('marketing.home.bike_lanes_sub') }}</p>
                <div class="bike-lanes__note">
                    <span>{{ __('marketing.home.bike_lanes_note') }}</span>
                    <a href="https://itdp.org/2025/09/16/a-more-walkable-cyclable-and-accessible-future-for-addis-ababa/" target="_blank" rel="noopener noreferrer">{{ __('marketing.home.bike_lanes_source') }}</a>
                </div>
            </div>
        </section>

        <section id="how" class="simple-how reveal">
            <div class="simple-how__intro">
                <h2 class="section-title motion-heading"><span>{{ __('marketing.home.simple_how_title') }}</span></h2>
                <p class="section-sub">{{ __('marketing.home.simple_how_sub') }}</p>
            </div>
            <div class="simple-steps">
                <article>
                    <strong>01</strong>
                    <h3>{{ __('marketing.home.simple_step_1_title') }}</h3>
                    <p>{{ __('marketing.home.simple_step_1_desc') }}</p>
                </article>
                <article>
                    <strong>02</strong>
                    <h3>{{ __('marketing.home.simple_step_2_title') }}</h3>
                    <p>{{ __('marketing.home.simple_step_2_desc') }}</p>
                </article>
                <article>
                    <strong>03</strong>
                    <h3>{{ __('marketing.home.simple_step_3_title') }}</h3>
                    <p>{{ __('marketing.home.simple_step_3_desc') }}</p>
                </article>
            </div>
        </section>

        <section id="download" class="cta simple-cta reveal">
            <div>
                <h2 class="motion-heading"><span>{{ __('marketing.home.cta_title') }}</span></h2>
                <p>{{ __('marketing.home.cta_sub', ['app' => config('app.name', 'BISKLET')]) }}</p>
            </div>
            <div class="cta-store-badges">
                <div class="cta-store" role="status" aria-label="{{ __('marketing.home.store_app_store_top') }} {{ __('marketing.home.store_app_store') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16.7 12.7c0-2.6 2.1-3.8 2.2-3.9-1.2-1.8-3.1-2-3.8-2-1.6-.2-3.2 1-4 .9-.9 0-2.1-.9-3.5-.9-1.8 0-3.5 1.1-4.5 2.7-1.9 3.3-.5 8.2 1.4 10.9.9 1.3 2 2.8 3.5 2.7 1.4-.1 2-.9 3.7-.9s2.2.9 3.7.8c1.5 0 2.5-1.3 3.4-2.7 1.1-1.6 1.5-3.1 1.5-3.2-.1 0-3.6-1.4-3.6-5.4ZM14.1 5.1c.8-1 1.3-2.3 1.2-3.6-1.2.1-2.6.8-3.4 1.8-.7.8-1.4 2.2-1.2 3.4 1.3.1 2.6-.6 3.4-1.6Z"></path>
                    </svg>
                    <span><small>{{ __('marketing.home.store_app_store_top') }}</small><strong>{{ __('marketing.home.store_app_store') }}</strong></span>
                </div>
                <div class="cta-store" role="status" aria-label="{{ __('marketing.home.store_google_top') }} {{ __('marketing.home.store_google') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 3.5v17l12-8.5L4 3.5Zm13.5 7.5 2.2 1.6c.4.3.4.6 0 .9l-2.2 1.5-2.1-2 2.1-2Zm-12 10.2 8.8-6.2 2 1.9-9.6 5.5c-.6.3-1.1 0-1.2-1.2Zm8.8-12.1-8.8-6.2c.1-1.1.6-1.5 1.2-1.2l9.6 5.5-2 1.9Z"></path>
                    </svg>
                    <span><small>{{ __('marketing.home.store_google_top') }}</small><strong>{{ __('marketing.home.store_google') }}</strong></span>
                </div>
            </div>
        </section>
    </div>
@endsection
