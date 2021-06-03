<?php

namespace IN10\ApiGatewayDrupal\Middleware;

use App;
use Carbon\Carbon;
use Closure;

class Localization
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($locale = $request->route('locale')) {
            App::setLocale($locale);

            switch ($locale) {
                case 'nl':
                    setlocale(LC_ALL, 'nl_NL.utf8');
                    break;
                case 'de':
                    setlocale(LC_ALL, 'de_DE.utf8');
                    break;
                case 'fr':
                    setlocale(LC_ALL, 'fr_FR.utf8');
                    break;
                default:
                    setlocale(LC_ALL, 'en_US.utf8');
                    break;
            }

            Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
