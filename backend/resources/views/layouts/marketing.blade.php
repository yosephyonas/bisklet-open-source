<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $siteName = config('app.name', 'BISKLET');
        $defaultTitle = __('marketing.meta.title_default', ['app' => $siteName]);
        $metaTitle = trim($__env->yieldContent('title', $defaultTitle));
        $metaDescription = trim($__env->yieldContent('meta_description', __('marketing.meta.description', ['app' => $siteName])));
        $metaKeywords = trim($__env->yieldContent('meta_keywords', "bike sharing, dockless bike sharing, ebike sharing, bicycle rental app, micromobility, shared bikes, Addis Ababa bike sharing, sustainable transport"));
        $metaRobots = trim($__env->yieldContent('meta_robots', 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'));
        $metaType = trim($__env->yieldContent('meta_type', 'website'));
        $metaUrl = url()->current();
        $metaImage = trim($__env->yieldContent('meta_image', asset('favicon.ico')));
        $currentLocale = app()->getLocale();
        $metaLocale = $currentLocale === 'am' ? 'am_ET' : 'en_US';
        $organizationSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => url('/'),
            'logo' => asset('favicon.ico'),
            'description' => $metaDescription,
        ];
        $webPageSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $metaTitle,
            'url' => $metaUrl,
            'description' => $metaDescription,
            'inLanguage' => $currentLocale,
            'keywords' => $metaKeywords,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => url('/'),
            ],
        ];
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="keywords" content="{{ $metaKeywords }}">
    <meta name="robots" content="{{ $metaRobots }}">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="{{ config('branding.colors.primary_teal') }}">
    <link rel="canonical" href="{{ $metaUrl }}">

    <meta property="og:type" content="{{ $metaType }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="{{ $metaLocale }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $metaUrl }}">
    <meta property="og:image" content="{{ $metaImage }}">
    <meta property="og:image:alt" content="{{ $metaTitle }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $metaImage }}">
    <meta name="twitter:image:alt" content="{{ $metaTitle }}">
    <meta name="twitter:site" content="@BISKLET">
    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($webPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @yield('structured_data')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">

    <script>
        document.documentElement.classList.add('js');
    </script>
    <style>
        :root {
            --bg: var(--teal-extra-light);
            --bg-soft: var(--teal-extra-light);
            --ink: var(--neutral-ink);
            --muted: var(--neutral-muted);
            --primary: var(--teal-primary);
            --accent: var(--teal-deep);
            --primary-soft: rgb(var(--teal-primary-rgb) / 0.14);
            --accent-soft: rgb(var(--teal-deep-rgb) / 0.16);
            --white: var(--neutral-white);
            --dark: var(--teal-extra-dark);
            --dark-elevated: var(--teal-dark);
            --shadow: 0 24px 60px rgb(var(--teal-extra-dark-rgb) / 0.2);
            --radius-lg: 28px;
            --radius-sm: 14px;
            --grid: clamp(16px, 3.6vw, 32px);
        }

        * {
            box-sizing: border-box;
        }

        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            max-width: 100%;
            overflow-x: clip;
        }

        body {
            margin: 0;
            font-family: "Space Grotesk", "Bricolage Grotesque", sans-serif;
            color: var(--ink);
            background: var(--bg);
            min-height: 100vh;
            overscroll-behavior-y: contain;
            overflow-x: clip;
            max-width: 100%;
            text-size-adjust: 100%;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        img {
            max-width: 100%;
            display: block;
        }

        .page {
            max-width: 1360px;
            margin: 0 auto;
            padding:
                max(clamp(20px, 4vw, 36px), env(safe-area-inset-top))
                max(clamp(20px, 4vw, 36px), env(safe-area-inset-right))
                max(clamp(20px, 4vw, 36px), env(safe-area-inset-bottom))
                max(clamp(20px, 4vw, 36px), env(safe-area-inset-left));
            position: relative;
            width: min(1360px, 100%);
            overflow-x: clip;
        }

        .page::before,
        .page::after {
            content: "";
            position: absolute;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            opacity: 0.32;
            z-index: 0;
            pointer-events: none;
            will-change: transform;
        }

        .page::before {
            background: rgb(var(--teal-primary-rgb) / 0.18);
            top: -120px;
            left: -140px;
            animation: orbDrift 32s ease-in-out infinite;
        }

        .page::after {
            background: rgb(var(--teal-deep-rgb) / 0.18);
            bottom: -160px;
            right: -120px;
            animation: orbDriftTwo 36s ease-in-out infinite;
        }

        .page > * {
            position: relative;
            z-index: 1;
            min-width: 0;
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            position: relative;
            z-index: 2;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: clamp(8px, 1.4vw, 12px);
            font-family: "Bricolage Grotesque", sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
            min-width: 0;
        }

        .logo {
            width: clamp(34px, 3.4vw, 40px);
            height: clamp(34px, 3.4vw, 40px);
            border-radius: clamp(12px, 1.3vw, 14px);
            background: var(--primary);
            display: grid;
            place-items: center;
            font-size: clamp(16px, 1.5vw, 18px);
            color: var(--white);
            box-shadow: inset 0 0 0 1px rgb(var(--neutral-white-rgb) / 0.25);
            flex: 0 0 auto;
        }

        .brand span {
            font-size: clamp(23px, 2.4vw, 32px);
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-left {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 18px;
            font-size: 15px;
            color: var(--muted);
        }

        .nav-links a {
            padding-bottom: 2px;
            border-bottom: 2px solid transparent;
            transition: border-color 0.2s ease, color 0.2s ease;
        }

        .nav-links a:hover {
            border-color: var(--ink);
            color: var(--ink);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-toggle {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.14);
            background: rgb(var(--neutral-white-rgb) / 0.88);
            color: var(--ink);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .menu-toggle span {
            display: block;
            width: 18px;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
            position: relative;
        }

        .menu-toggle span::before,
        .menu-toggle span::after {
            content: "";
            position: absolute;
            left: 0;
            width: 18px;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
        }

        .menu-toggle span::before { top: -6px; }
        .menu-toggle span::after { top: 6px; }

        .side-menu-overlay {
            position: fixed;
            inset: 0;
            background: rgb(var(--teal-extra-dark-rgb) / 0.42);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.24s ease;
            z-index: 90;
        }

        .side-menu {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: min(320px, 86vw);
            background: rgb(var(--neutral-white-rgb) / 0.98);
            border-left: 1px solid rgb(var(--neutral-ink-rgb) / 0.1);
            box-shadow: -18px 0 40px rgb(var(--teal-extra-dark-rgb) / 0.2);
            transform: translateX(100%);
            transition: transform 0.26s ease;
            z-index: 100;
            padding: 18px 16px 22px;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 16px;
        }

        .side-menu-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .side-menu-title {
            margin: 0;
            font-size: 13px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }

        .side-menu-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.14);
            background: var(--white);
            color: var(--ink);
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }

        .menu-actions {
            display: grid;
            gap: 10px;
        }

        .menu-actions .btn {
            justify-content: center;
            width: 100%;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            padding: 11px 12px;
        }

        .lang {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px;
            border-radius: 12px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.1);
            background: rgb(var(--neutral-white-rgb) / 0.86);
            width: max-content;
        }

        .lang a {
            border: 0;
            background: transparent;
            color: var(--muted);
            padding: 7px 10px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.01em;
            cursor: pointer;
        }

        .lang a.is-active {
            background: rgb(var(--teal-deep-rgb) / 0.14);
            color: var(--primary);
        }

        .header-lang {
            padding: 4px;
            border-radius: 10px;
        }

        .header-lang a {
            font-size: 12px;
            padding: 6px 8px;
            min-width: 34px;
            text-align: center;
        }

        .side-menu-links {
            display: grid;
            align-content: start;
            gap: 8px;
        }

        .side-menu-links a {
            padding: 9px 10px;
            border-radius: 10px;
            color: var(--ink);
            font-weight: 500;
        }

        .side-menu-links a:hover {
            background: rgb(var(--neutral-ink-rgb) / 0.05);
        }

        body.menu-open {
            overflow: hidden;
        }

        body.menu-open .side-menu-overlay {
            opacity: 1;
            pointer-events: auto;
        }

        body.menu-open .side-menu {
            transform: translateX(0);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid transparent;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            white-space: nowrap;
        }

        .btn.primary {
            background: var(--accent);
            color: var(--white);
            box-shadow: 0 12px 24px rgb(var(--teal-deep-rgb) / 0.35);
            position: relative;
            overflow: hidden;
        }

        .btn.primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgb(var(--teal-deep-rgb) / 0.42);
        }

        .btn.primary::after {
            content: "";
            position: absolute;
            top: -60%;
            left: -40%;
            width: 60%;
            height: 220%;
            background: transparent;
            transform: translateX(-140%) rotate(18deg);
            animation: shine 9s ease-in-out infinite;
            pointer-events: none;
        }

        .btn.ghost {
            border-color: rgb(var(--teal-primary-rgb) / 0.4);
            color: var(--primary);
            background: rgb(var(--teal-primary-rgb) / 0.08);
        }

        .btn.ghost:hover {
            background: rgb(var(--teal-primary-rgb) / 0.16);
            transform: translateY(-1px);
        }

        .hero {
            margin-top: clamp(32px, 7vw, 72px);
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            gap: clamp(24px, 6vw, 60px);
            position: relative;
        }

        @media (min-width: 1280px) {
            .hero {
                min-height: clamp(560px, 72vh, 760px);
                align-items: center;
            }
        }

        .hero::before {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: var(--teal-light);
            right: 36%;
            top: -40px;
            z-index: 0;
            filter: blur(1px);
            animation: float 18s ease-in-out infinite;
            will-change: transform;
        }

        .hero-copy {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 600;
            color: var(--muted);
        }

        .eyebrow span {
            width: 26px;
            height: 2px;
            background: var(--ink);
        }

        h1 {
            font-family: "Bricolage Grotesque", sans-serif;
            font-size: clamp(38px, 6vw, 64px);
            line-height: 1.05;
            margin: 18px 0 16px;
            letter-spacing: -0.03em;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .hero h1 .hero-highlight {
            color: var(--accent);
        }

        .lead {
            font-size: 18px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 26px;
            overflow-wrap: anywhere;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }

        .btn.secondary {
            background: var(--white);
            border-color: rgb(var(--teal-primary-rgb) / 0.3);
            color: var(--primary);
        }

        .btn.secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgb(var(--teal-primary-rgb) / 0.18);
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .stat {
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            background: rgb(var(--neutral-white-rgb) / 0.8);
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.08);
        }

        .stat strong {
            display: block;
            font-size: 20px;
            font-weight: 700;
        }

        .stat span {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .hero-visual {
            position: relative;
            display: grid;
            place-items: center;
            gap: 16px;
        }

        .ride-card {
            width: 100%;
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.12);
            position: relative;
            overflow: hidden;
            animation: cardFloat 16s ease-in-out infinite;
            transition: transform 0.6s ease, box-shadow 0.6s ease;
            will-change: transform;
        }

        .ride-card::after {
            content: "";
            position: absolute;
            inset: -40% 40% auto -40%;
            height: 80%;
            background: rgb(var(--teal-deep-rgb) / 0.18);
            opacity: 0.7;
            animation: sweep 12s ease-in-out infinite;
            will-change: transform;
        }

        .ride-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 28px 70px rgb(var(--teal-extra-dark-rgb) / 0.24);
        }

        .ride-card h3 {
            margin: 0 0 6px;
            font-family: "Bricolage Grotesque", sans-serif;
            font-size: 22px;
        }

        .ride-card p {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .map {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 18px;
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            isolation: isolate;
        }

        .map span {
            height: 32px;
            border-radius: 12px;
            background: rgb(var(--neutral-ink-rgb) / 0.05);
        }

        .map::after {
            content: "";
            position: absolute;
            inset: 0;
            background: rgb(var(--teal-primary-rgb) / 0.08);
            transform: translateX(-130%);
            animation: nearbyShimmer 2.8s ease-in-out infinite;
            pointer-events: none;
            z-index: 2;
        }

        .map span.active {
            background: var(--primary);
            animation: signal 4.5s ease-in-out infinite;
        }

        .pulse {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--muted);
        }

        .pulse-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            box-shadow: 0 0 0 6px rgb(var(--teal-primary-rgb) / 0.2);
            animation: pulse 2s infinite;
        }

        .sections {
            margin-top: clamp(48px, 8vw, 96px);
            display: grid;
            gap: clamp(40px, 6vw, 64px);
        }

        .sections > section {
            min-width: 0;
        }

        .section-title {
            font-family: "Bricolage Grotesque", sans-serif;
            font-size: clamp(26px, 4vw, 34px);
            margin: 0 0 12px;
            overflow-wrap: anywhere;
        }

        .section-sub {
            color: var(--muted);
            margin: 0 0 24px;
            max-width: 620px;
            overflow-wrap: anywhere;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: clamp(10px, 1.6vw, 14px);
        }

        .step-card {
            background: rgb(var(--neutral-white-rgb) / 0.95);
            border-radius: var(--radius-lg);
            padding: 14px 14px 12px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.07);
            box-shadow: 0 8px 14px rgb(var(--neutral-ink-rgb) / 0.06);
            position: relative;
            transition: transform 0.6s ease, box-shadow 0.6s ease;
            text-align: center;
            overflow: hidden;
        }

        .step-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 18px;
            right: 18px;
            height: 3px;
            border-radius: 999px;
            background: var(--teal-primary);
            opacity: 0.9;
        }

        .step-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 22px rgb(var(--teal-extra-dark-rgb) / 0.11);
        }

        .step-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--accent);
            color: var(--white);
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            box-shadow: 0 8px 16px rgb(var(--teal-deep-rgb) / 0.28);
        }

        .step-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: var(--neutral-white);
            display: grid;
            place-items: center;
            margin: 2px auto 8px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.06);
            box-shadow: 0 8px 16px rgb(var(--teal-extra-dark-rgb) / 0.07);
        }

        .step-icon svg {
            width: 22px;
            height: 22px;
            stroke: var(--accent);
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .step-card h3 {
            margin: 0 0 6px;
            line-height: 1.1;
            letter-spacing: -0.02em;
            font-family: "Bricolage Grotesque", sans-serif;
            font-size: clamp(16px, 1.8vw, 22px);
            overflow-wrap: anywhere;
        }

        .step-card p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.35;
            max-width: 32ch;
            margin-inline: auto;
            overflow-wrap: anywhere;
        }

        .mission-quote {
            background: rgb(var(--neutral-white-rgb) / 0.88);
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.08);
            border-radius: clamp(20px, 3vw, 28px);
            padding: clamp(20px, 3.2vw, 34px) clamp(16px, 3.8vw, 40px);
            text-align: center;
            box-shadow: 0 14px 30px rgb(var(--teal-extra-dark-rgb) / 0.1);
            max-width: 980px;
            margin-inline: auto;
        }

        .mission-quote blockquote {
            margin: 0;
            font-family: "Bricolage Grotesque", sans-serif;
            font-size: clamp(18px, 2.5vw, 24px);
            line-height: 1.45;
            letter-spacing: -0.01em;
            color: var(--neutral-ink);
            max-width: 34ch;
            margin-inline: auto;
            overflow-wrap: anywhere;
        }

        .mission-quote__author {
            margin: clamp(12px, 2vw, 18px) 0 0;
            color: var(--neutral-text);
            font-weight: 600;
            font-size: clamp(15px, 1.5vw, 18px);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--grid);
        }

        .feature {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 22px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.1);
            display: grid;
            gap: 10px;
            transition: transform 0.6s ease, box-shadow 0.6s ease;
        }

        .feature:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 36px rgb(var(--teal-extra-dark-rgb) / 0.16);
        }

        .feature h4 {
            margin: 0;
            font-size: 18px;
        }

        .feature p {
            margin: 0;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .fleet {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--grid);
        }

        .fleet-card {
            border-radius: var(--radius-lg);
            padding: 22px;
            background: rgb(var(--neutral-white-rgb) / 0.85);
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.08);
            transition: transform 0.6s ease, box-shadow 0.6s ease;
        }

        .fleet-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 36px rgb(var(--teal-extra-dark-rgb) / 0.16);
        }

        .fleet-card span {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .coverage {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 28px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.1);
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            gap: var(--grid);
            transition: transform 0.6s ease, box-shadow 0.6s ease;
        }

        .coverage:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 42px rgb(var(--teal-extra-dark-rgb) / 0.16);
        }

        .map-board {
            border-radius: 20px;
            background: var(--teal-extra-light);
            min-height: 220px;
            position: relative;
            overflow: hidden;
        }

        .map-board::after {
            content: "";
            position: absolute;
            inset: 20% 10% auto 10%;
            height: 120px;
            border-radius: 100px;
            background: rgb(var(--teal-primary-rgb) / 0.18);
            transform: rotate(-6deg);
            animation: drift 20s ease-in-out infinite;
            will-change: transform;
        }

        .map-board--routes {
            display: grid;
            place-items: center;
            padding: 22px;
        }

        .map-board__art {
            width: 100%;
            height: 100%;
            max-height: 260px;
        }

        .map-road {
            fill: none;
            stroke: rgb(var(--teal-primary-rgb) / 0.35);
            stroke-width: 3;
            stroke-linecap: round;
            stroke-dasharray: 6 12;
            animation: roadFlow 18s linear infinite;
        }

        .map-zone {
            fill: rgb(var(--teal-deep-rgb) / 0.12);
            stroke: rgb(var(--teal-deep-rgb) / 0.3);
            stroke-width: 1.5;
        }

        .map-pin {
            fill: var(--accent);
        }

        .map-pin.primary {
            fill: var(--primary);
        }

        .map-pulse {
            fill: rgb(var(--teal-deep-rgb) / 0.28);
            transform-box: fill-box;
            transform-origin: center;
            animation: dotPing 9s ease-in-out infinite;
        }

        .map-pulse.primary {
            fill: rgb(var(--teal-primary-rgb) / 0.32);
        }

        .map-label {
            font-size: 12px;
            fill: var(--ink);
            font-weight: 600;
        }

        .map-label.secondary {
            fill: var(--muted);
            font-weight: 500;
        }

        .map-dot {
            --ping-delay: 0s;
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 4px rgb(var(--teal-deep-rgb) / 0.16);
        }

        .map-dot::after {
            content: "";
            position: absolute;
            inset: -9px;
            border-radius: 50%;
            border: 1px solid rgb(var(--teal-deep-rgb) / 0.5);
            opacity: 0;
            transform: scale(0.7);
            animation: dotPing 9s ease-in-out infinite;
            animation-delay: var(--ping-delay);
            will-change: transform, opacity;
        }

        .map-dot.one { top: 32%; left: 18%; --ping-delay: 0.4s; }
        .map-dot.two { top: 58%; left: 52%; --ping-delay: 1.2s; }
        .map-dot.three { top: 22%; right: 20%; background: var(--primary); box-shadow: 0 0 0 4px rgb(var(--teal-primary-rgb) / 0.2); --ping-delay: 2s; }
        .map-dot.three::after { border-color: rgb(var(--teal-primary-rgb) / 0.55); }

        .pricing {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--grid);
        }

        .price-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.1);
            display: grid;
            gap: 16px;
            transition: transform 0.6s ease, box-shadow 0.6s ease;
        }

        .price-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 42px rgb(var(--teal-extra-dark-rgb) / 0.16);
        }

        .price-card.highlight {
            background: var(--teal-extra-light);
            border-color: rgb(var(--teal-primary-rgb) / 0.25);
            transform: translateY(-8px);
        }

        .price-card h3 {
            margin: 0;
            font-size: 20px;
        }

        .price {
            font-size: 32px;
            font-weight: 700;
        }

        .price small {
            font-size: 14px;
            color: var(--muted);
        }

        .safety-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--grid);
        }

        .safety-card {
            background: rgb(var(--neutral-white-rgb) / 0.9);
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.08);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: 0 12px 24px rgb(var(--teal-extra-dark-rgb) / 0.08);
            transition: transform 0.5s ease, box-shadow 0.5s ease;
        }

        .safety-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 34px rgb(var(--teal-extra-dark-rgb) / 0.14);
        }

        .safety-card h3 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .safety-card p {
            margin: 0;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .responsibility {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 26px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.1);
            box-shadow: 0 14px 30px rgb(var(--teal-extra-dark-rgb) / 0.08);
            display: grid;
            gap: 20px;
        }

        .responsibility-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .rule-card {
            border-radius: 18px;
            padding: 18px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.08);
            background: rgb(var(--neutral-surface-rgb) / 0.8);
        }

        .rule-card h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }

        .rule-card ul {
            margin: 0;
            padding-left: 18px;
            display: grid;
            gap: 8px;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .rule-card.do h3 {
            color: var(--status-success);
        }

        .rule-card.dont h3 {
            color: var(--status-error);
        }

        .download-trust {
            border-radius: var(--radius-lg);
            background: rgb(var(--neutral-white-rgb) / 0.94);
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.09);
            padding: 24px;
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
            gap: 20px;
            align-items: center;
        }

        .download-copy .section-sub {
            margin-bottom: 18px;
        }

        .store-badges {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .store-badge {
            border-radius: 14px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.12);
            padding: 12px 14px;
            background: var(--white);
            display: grid;
            gap: 4px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .store-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 22px rgb(var(--teal-extra-dark-rgb) / 0.12);
        }

        .store-badge small {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .store-badge strong {
            font-size: 18px;
            font-family: "Bricolage Grotesque", sans-serif;
            overflow-wrap: anywhere;
        }

        .qr-card {
            justify-self: end;
            width: min(260px, 100%);
            background: var(--white);
            border-radius: 18px;
            border: 1px solid rgb(var(--neutral-ink-rgb) / 0.1);
            padding: 16px;
            text-align: center;
            box-shadow: 0 14px 24px rgb(var(--teal-extra-dark-rgb) / 0.08);
        }

        .qr-grid {
            width: 144px;
            margin: 0 auto 10px;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 4px;
        }

        .qr-grid span {
            aspect-ratio: 1;
            border-radius: 3px;
            background: var(--teal-light);
        }

        .qr-grid span.on {
            background: var(--teal-extra-dark);
        }

        .qr-card p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .cta {
            border-radius: 32px;
            padding: clamp(28px, 6vw, 48px);
            background: var(--dark);
            color: var(--white);
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
            gap: 24px;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .cta::after {
            content: "";
            position: absolute;
            inset: -30%;
            background: rgb(var(--teal-primary-rgb) / 0.22);
            opacity: 0.5;
            animation: ctaGlow 14s ease-in-out infinite;
            pointer-events: none;
            will-change: transform;
        }

        .cta > * {
            position: relative;
            z-index: 1;
        }

        .cta h2 {
            margin: 0 0 12px;
            font-family: "Bricolage Grotesque", sans-serif;
            font-size: clamp(28px, 4vw, 40px);
            overflow-wrap: anywhere;
        }

        .cta p {
            margin: 0;
            color: rgb(var(--neutral-white-rgb) / 0.7);
            overflow-wrap: anywhere;
        }

        .cta .btn {
            justify-content: center;
        }

        footer {
            margin: 28px 0 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            color: var(--muted);
            font-size: 14px;
            border-top: 1px solid rgb(var(--neutral-ink-rgb) / 0.08);
            padding-top: 14px;
        }

        .footer-block {
            display: grid;
            gap: 4px;
        }

        .footer-links {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: var(--neutral-text);
            font-weight: 500;
        }

        .footer-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .reveal {
            opacity: 1;
            transform: translateY(0);
        }

        .js .reveal {
            opacity: 0;
            transform: translateY(18px);
            transition: opacity 1s ease, transform 1s ease;
        }

        .js .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .js .reveal.delay-1 { transition-delay: 0.15s; }
        .js .reveal.delay-2 { transition-delay: 0.3s; }
        .js .reveal.delay-3 { transition-delay: 0.45s; }
        .js .reveal.delay-4 { transition-delay: 0.6s; }


        @keyframes pulse {
            0% { box-shadow: 0 0 0 6px rgb(var(--teal-primary-rgb) / 0.2); }
            70% { box-shadow: 0 0 0 14px rgb(var(--teal-primary-rgb) / 0); }
            100% { box-shadow: 0 0 0 6px rgb(var(--teal-primary-rgb) / 0); }
        }

        @keyframes orbDrift {
            0% { transform: translate(0, 0); }
            50% { transform: translate(30px, 18px); }
            100% { transform: translate(0, 0); }
        }

        @keyframes orbDriftTwo {
            0% { transform: translate(0, 0); }
            50% { transform: translate(-24px, -16px); }
            100% { transform: translate(0, 0); }
        }

        @keyframes float {
            0% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(8px) translateX(-6px); }
            100% { transform: translateY(0) translateX(0); }
        }

        @keyframes cardFloat {
            0% { transform: translateY(0); }
            50% { transform: translateY(6px); }
            100% { transform: translateY(0); }
        }

        @keyframes sweep {
            0% { transform: translateX(0); }
            50% { transform: translateX(12px); }
            100% { transform: translateX(0); }
        }

        @keyframes drift {
            0% { transform: translateX(0) rotate(-6deg); }
            50% { transform: translateX(10px) rotate(-4deg); }
            100% { transform: translateX(0) rotate(-6deg); }
        }

        @keyframes signal {
            0% { filter: brightness(1); }
            50% { filter: brightness(1.08); }
            100% { filter: brightness(1); }
        }

        @keyframes shine {
            0% { transform: translateX(-140%) rotate(18deg); }
            45% { transform: translateX(220%) rotate(18deg); }
            100% { transform: translateX(220%) rotate(18deg); }
        }

        @keyframes dotPing {
            0% { transform: scale(0.7); opacity: 0; }
            45% { transform: scale(1.35); opacity: 0.6; }
            100% { transform: scale(1.6); opacity: 0; }
        }

        @keyframes roadFlow {
            0% { stroke-dashoffset: 0; }
            100% { stroke-dashoffset: -180; }
        }

        @keyframes ctaGlow {
            0% { transform: translateX(-4%) translateY(0); opacity: 0.4; }
            50% { transform: translateX(4%) translateY(-3%); opacity: 0.6; }
            100% { transform: translateX(-4%) translateY(0); opacity: 0.4; }
        }

        @keyframes nearbyShimmer {
            0% { transform: translateX(-130%); }
            55% { transform: translateX(130%); }
            100% { transform: translateX(130%); }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            .reveal {
                opacity: 1;
                transform: none;
                animation: none;
            }

            .pulse-dot {
                animation: none;
            }

            .hero::before,
            .page::before,
            .page::after,
            .ride-card,
            .ride-card::after,
            .map-road,
            .map span.active,
            .map::after,
            .map-dot,
            .map-board::after,
            .map-pulse,
            .cta {
                animation: none;
            }

            .map-dot::after {
                animation: none;
                opacity: 0;
            }

            .step-card,
            .feature,
            .fleet-card,
            .price-card,
            .coverage {
                transition: none;
            }

            .btn.primary::after,
            .cta::after {
                animation: none;
            }
        }

        @media (max-width: 1100px) {
            .page {
                max-width: 1040px;
            }

            .nav {
                gap: 14px;
                row-gap: 10px;
            }

            .nav-links {
                gap: 10px 14px;
                font-size: 14px;
            }

            .btn {
                padding: 11px 16px;
            }

            .hero {
                gap: clamp(18px, 4vw, 34px);
            }

            .section-sub {
                max-width: 100%;
            }

            .sections {
                gap: clamp(28px, 4.5vw, 46px);
            }

            .steps,
            .features,
            .fleet,
            .pricing {
                gap: 18px;
            }

            .cta,
            .coverage {
                padding: 24px;
            }
        }

        @media (max-width: 860px) {
            .nav {
                flex-wrap: wrap;
                align-items: center;
            }

            .nav-links {
                width: 100%;
                order: 3;
                justify-content: flex-start;
                flex-wrap: wrap;
                padding-top: 4px;
            }

            .nav-actions {
                margin-left: auto;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .nav-actions .btn {
                justify-content: center;
                min-width: 0;
            }

            .menu-toggle {
                display: inline-flex;
            }

            .nav-actions {
                display: none;
            }

            .side-menu,
            .side-menu-overlay {
                display: block;
            }
        }

        @media (max-width: 980px) {
            .nav {
                flex-wrap: wrap;
            }

            .hero {
                grid-template-columns: 1fr;
            }

            .coverage {
                grid-template-columns: 1fr;
            }

            .pricing {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .download-trust {
                grid-template-columns: 1fr;
            }

            .qr-card {
                justify-self: start;
            }
        }

        @media (max-width: 760px) {
            .page {
                padding: 14px;
            }

            .nav-links {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
                gap: 10px 14px;
                font-size: 14px;
            }

            .nav-actions {
                display: none;
            }

            .nav-actions .btn {
                justify-content: center;
                width: auto;
                padding: 10px 16px;
                font-weight: 700;
                border-radius: 12px;
            }

            .hero {
                margin-top: 18px;
            }

            h1 {
                font-size: clamp(26px, 8.4vw, 34px);
                line-height: 1.12;
            }

            .lead {
                font-size: 15px;
                line-height: 1.5;
                margin-bottom: 14px;
            }

            .hero-actions {
                margin-bottom: 14px;
                gap: 8px;
            }

            .hero-actions .btn {
                width: auto;
                justify-content: center;
                padding: 9px 14px;
            }

            .hero-stats {
                grid-template-columns: 1fr;
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .fleet {
                grid-template-columns: 1fr;
            }

            .pricing {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            #pricing .section-sub {
                margin-bottom: 14px;
            }

            .price-card {
                padding: 16px;
                gap: 10px;
                border-radius: 22px;
            }

            .price-card h3 {
                font-size: 16px;
            }

            .price {
                font-size: 26px;
                line-height: 1.1;
            }

            .price small {
                font-size: 12px;
            }

            .price-card p {
                font-size: 14px;
                line-height: 1.4;
            }

            .price-card .btn {
                width: 100%;
                padding: 8px 12px;
                font-size: 13px;
            }

            .safety-grid {
                grid-template-columns: 1fr;
            }

            .responsibility-grid {
                grid-template-columns: 1fr;
            }

            .cta {
                grid-template-columns: 1fr;
            }

            .cta,
            .coverage {
                padding: 20px;
            }

            .mission-quote {
                padding: 18px 14px;
            }

            .mission-quote blockquote {
                font-size: clamp(17px, 5vw, 20px);
                line-height: 1.45;
            }

            .mission-quote__author {
                font-size: 15px;
            }

            .store-badges {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .page {
                padding: 12px;
            }

            .brand span {
                font-size: 22px;
            }

            .nav {
                align-items: center;
                gap: 8px;
            }

            .nav-left {
                gap: 8px;
            }

            .header-lang a {
                min-width: 32px;
                padding: 5px 7px;
            }

            .eyebrow {
                font-size: 12px;
                letter-spacing: 0.08em;
            }

            .step-card {
                padding: 14px 12px 12px;
            }

            .step-badge {
                width: 24px;
                height: 24px;
                top: 8px;
                right: 8px;
                font-size: 11px;
            }

            .step-icon {
                width: 48px;
                height: 48px;
                margin-bottom: 8px;
            }

            .step-icon svg {
                width: 20px;
                height: 20px;
            }

            .step-card h3 {
                font-size: clamp(15px, 5.6vw, 18px);
            }

            .step-card p {
                font-size: 12px;
            }

            .fleet-card,
            .feature,
            .price-card {
                padding: 14px;
            }

            .price {
                font-size: 24px;
            }

            .price-card p {
                font-size: 13px;
            }

            .section-sub {
                margin-bottom: 18px;
            }

            footer {
                margin-top: 22px;
                text-align: center;
                justify-content: center;
                padding-top: 12px;
            }

            .footer-right {
                justify-content: center;
            }
        }

        @media (max-width: 420px) {
            .page {
                padding:
                    max(10px, env(safe-area-inset-top))
                    max(10px, env(safe-area-inset-right))
                    max(10px, env(safe-area-inset-bottom))
                    max(10px, env(safe-area-inset-left));
            }

            .brand {
                gap: 8px;
            }

            .brand span {
                font-size: 21px;
            }

            .logo {
                width: 35px;
                height: 35px;
                border-radius: 12px;
                font-size: 16px;
            }

            .menu-toggle {
                width: 40px;
                height: 40px;
            }

            .header-lang {
                padding: 3px;
            }

            .header-lang a {
                min-width: 30px;
                padding: 5px 6px;
                font-size: 11px;
            }

            .btn {
                font-size: 13px;
                padding: 10px 12px;
            }

            .section-title {
                font-size: clamp(22px, 7vw, 28px);
            }
        }

        @media (max-height: 760px) and (orientation: landscape) {
            .hero {
                margin-top: 20px;
            }

            .sections {
                margin-top: 32px;
                gap: 28px;
            }
        }

        /* Keep the page edge-to-edge on touch phones, even during pinch-zoom. */
        @media (hover: none) and (pointer: coarse) {
            .page {
                width: 100%;
                max-width: 100%;
                margin: 0;
            }
        }

        @media (min-width: 861px) {
            .side-menu,
            .side-menu-overlay {
                display: none;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/marketing-modern.css') }}">
    @yield('styles')
</head>
<body>
    <div class="page">
        <header class="nav reveal">
            <div class="nav-left">
                <a class="brand" href="{{ route('home') }}">
                    <span>{{ config('app.name', 'BISKLET') }}</span>
                </a>
                <div class="lang header-lang" aria-label="Header language switcher">
                    <a class="{{ $currentLocale === 'en' ? 'is-active' : '' }}" href="{{ route('language.switch', ['locale' => 'en']) }}">EN</a>
                    <a class="{{ $currentLocale === 'am' ? 'is-active' : '' }}" href="{{ route('language.switch', ['locale' => 'am']) }}">AM</a>
                </div>
            </div>
            <nav class="nav-links" aria-label="Primary navigation">
                <a href="{{ route('home') }}#places">{{ __('marketing.nav.places') }}</a>
                <a href="{{ route('home') }}#how">{{ __('marketing.nav.how') }}</a>
            </nav>
            <div class="nav-actions">
                <a class="btn primary" href="{{ route('home') }}#download">{{ __('marketing.nav.get_app') }}</a>
            </div>
            <button class="menu-toggle" type="button" aria-label="Open menu" aria-controls="mobile-menu" aria-expanded="false">
                <span aria-hidden="true"></span>
            </button>
        </header>

        <div class="side-menu-overlay" data-close-menu></div>
        <aside id="mobile-menu" class="side-menu" aria-hidden="true">
            <div class="side-menu-head">
                <p class="side-menu-title">{{ __('marketing.nav.menu') }}</p>
                <button class="side-menu-close" type="button" aria-label="{{ __('marketing.nav.close_menu') }}" data-close-menu>&times;</button>
            </div>
            <div class="menu-actions">
                <a class="btn primary" href="{{ route('home') }}#download">{{ __('marketing.nav.get_app') }}</a>
            </div>
            <nav class="side-menu-links" aria-label="{{ __('marketing.nav.mobile_links') }}">
                <a href="{{ route('home') }}#places">{{ __('marketing.nav.places') }}</a>
                <a href="{{ route('home') }}#how">{{ __('marketing.nav.how') }}</a>
            </nav>
        </aside>

        <main>
            @yield('content')
        </main>

        <footer class="reveal">
            <div class="footer-block">
                <div>&copy; {{ date('Y') }} {{ config('app.name', 'BISKLET') }}. {{ __('marketing.footer.rights') }}</div>
            </div>
            <div class="footer-right">
                <div class="lang" aria-label="Footer language switcher">
                    <a class="{{ $currentLocale === 'en' ? 'is-active' : '' }}" href="{{ route('language.switch', ['locale' => 'en']) }}">EN</a>
                    <a class="{{ $currentLocale === 'am' ? 'is-active' : '' }}" href="{{ route('language.switch', ['locale' => 'am']) }}">AM</a>
                </div>
            </div>
        </footer>
    </div>
    <script>
        (() => {
            const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const elements = document.querySelectorAll('.reveal');
            const body = document.body;
            const menuButton = document.querySelector('.menu-toggle');
            const menu = document.getElementById('mobile-menu');
            const closeTriggers = document.querySelectorAll('[data-close-menu]');

            window.requestAnimationFrame(() => body.classList.add('page-ready'));

            const setMenuState = (open) => {
                body.classList.toggle('menu-open', open);
                if (menuButton) {
                    menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
                if (menu) {
                    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
                }
            };

            if (menuButton && menu) {
                menuButton.addEventListener('click', () => {
                    const isOpen = body.classList.contains('menu-open');
                    setMenuState(!isOpen);
                });

                closeTriggers.forEach((trigger) => {
                    trigger.addEventListener('click', () => setMenuState(false));
                });

                menu.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', () => setMenuState(false));
                });
            }

            if (prefersReduced) {
                elements.forEach((el) => el.classList.add('is-visible'));
                return;
            }

            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                });
            }, { threshold: 0.18 });

            elements.forEach((el) => observer.observe(el));

            let progressFramePending = false;
            const updateScrollProgress = () => {
                const scrollable = document.documentElement.scrollHeight - window.innerHeight;
                const progress = scrollable > 0 ? window.scrollY / scrollable : 0;
                document.documentElement.style.setProperty('--scroll-progress', Math.max(0, Math.min(1, progress)));
                progressFramePending = false;
            };

            const requestScrollProgress = () => {
                if (!progressFramePending) {
                    progressFramePending = true;
                    window.requestAnimationFrame(updateScrollProgress);
                }
            };

            updateScrollProgress();
            window.addEventListener('scroll', requestScrollProgress, { passive: true });
            window.addEventListener('resize', requestScrollProgress);

        })();
    </script>
</body>
</html>
