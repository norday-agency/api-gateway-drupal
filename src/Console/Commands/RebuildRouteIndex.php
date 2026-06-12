<?php

namespace IN10\ApiGatewayDrupal\Console\Commands;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Console\Command;
use IN10\ApiGatewayDrupal\Services\RouteIndex;

/**
 * Build the route index from Drupal: walk every content type per locale and
 * collect each node's field_path. This is the only thing that marks the index
 * authoritative, so run it on deploy (and as the repair tool if a webhook is
 * ever dropped and the index drifts).
 */
class RebuildRouteIndex extends Command
{
    protected $signature = 'gateway:route-index:rebuild {--locale=* : Limit to specific locale(s)}';

    protected $description = 'Rebuild the route index (valid content paths) from Drupal, per locale';

    public function handle(): int
    {
        $locales = $this->option('locale') ?: config('app.supported_locales', []);

        if (empty($locales)) {
            $this->error('No locales to build (pass --locale or set app.supported_locales).');

            return self::FAILURE;
        }

        $hadFailure = false;

        foreach ($locales as $locale) {
            $paths = [];
            $failed = [];

            foreach (array_keys(config('cms.types', [])) as $type) {
                $result = $this->fetchPaths($locale, $type);
                if (! $result['ok']) {
                    $failed[] = $type;
                    continue;
                }
                $paths = array_merge($paths, $result['paths']);
            }

            // All-or-nothing: a partial index would 404 real pages whose type
            // failed to fetch. If anything failed, leave the existing index in
            // place and report failure instead of committing an incomplete set.
            if ($failed !== []) {
                $this->error(
                    "[{$locale}] rebuild ABORTED — " . count($failed)
                    . ' type(s) failed: ' . implode(', ', $failed) . '. Index left unchanged.'
                );
                $hadFailure = true;
                continue;
            }

            $count = RouteIndex::rebuild($locale, $paths);
            $this->info("[{$locale}] indexed {$count} paths");
        }

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Paginate a content type's JSON:API listing and collect field_path values.
     *
     * @return array{paths: string[], ok: bool} ok=false means a page request
     *         failed (timeout / connection / non-2xx); the caller must then
     *         treat the whole rebuild as failed rather than commit a partial set.
     */
    protected function fetchPaths(string $locale, string $type): array
    {
        $client = new GuzzleClient(array_merge(
            in_array(config('app.env'), ['local', 'test'], true) ? ['verify' => false] : [],
            [
                'connect_timeout' => (int) config('cms.http.connect_timeout', 2),
                // Allow longer than a request timeout — this is a batch job.
                'timeout' => (int) config('cms.http.rebuild_timeout', 30),
                // Inspect the status ourselves so a non-2xx counts as a failure
                // (and aborts the rebuild) rather than throwing.
                'http_errors' => false,
            ]
        ));

        $headers = ['Accept' => 'application/vnd.api+json'];
        if (config('cms.useBasicAuthentication') && config('cms.BasicAuthBase64String')) {
            $headers['Authorization'] = 'Basic ' . config('cms.BasicAuthBase64String');
        }

        $paths = [];
        $offset = 0;
        $limit = 50;

        do {
            $url = (string) new Uri(
                config('cms.url') . '/' . $locale . config('cms.endpoint') . '/' . $type
                . '?page%5Boffset%5D=' . $offset . '&page%5Blimit%5D=' . $limit
            );

            try {
                $response = $client->get($url, ['headers' => $headers]);
            } catch (\Throwable $e) {
                $this->warn("  [{$locale}/{$type}] fetch failed: " . $e->getMessage());

                return ['paths' => [], 'ok' => false];
            }

            if ($response->getStatusCode() >= 300) {
                $this->warn("  [{$locale}/{$type}] HTTP {$response->getStatusCode()}");

                return ['paths' => [], 'ok' => false];
            }

            $body = json_decode((string) $response->getBody(), true);
            $items = $body['data'] ?? [];

            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $fieldPath = $item['attributes']['field_path'] ?? null;
                if (is_string($fieldPath) && $fieldPath !== '') {
                    $paths[] = $fieldPath;
                }
            }

            $offset += $limit;
        } while (count($items) === $limit);

        return ['paths' => $paths, 'ok' => true];
    }
}
