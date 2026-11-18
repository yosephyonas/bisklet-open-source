<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = ['en', 'am'];
        $locale = (string) $request->session()->get('locale', config('app.locale', 'en'));

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
