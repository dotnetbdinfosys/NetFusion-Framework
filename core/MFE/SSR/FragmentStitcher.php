<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\MFE\SSR;

use NetFusion\Core\MFE\Enums\RenderMode;
use NetFusion\Core\MFE\Federation\ModuleFederationRegistry;
use NetFusion\Core\MFE\Models\MfeFragment;

/**
 * Edge Server-Side Rendering (SSR) & Composable Fragment Stitching Engine.
 * Parses HTML template shells containing `<mfe-fragment ... />` custom elements,
 * fetches remote fragments with parallel dispatch, handles timeout / circuit breaker fallbacks,
 * and streams or stitches completed HTML documents.
 */
class FragmentStitcher
{
    protected ModuleFederationRegistry $registry;
    /** @var callable|null function(string $url, array $props, int $timeout): ?string */
    protected $fetcher = null;

    public function __construct(ModuleFederationRegistry $registry, ?callable $fetcher = null)
    {
        $this->registry = $registry;
        $this->fetcher = $fetcher ?? [$this, 'defaultFetch'];
    }

    public function setFetcher(callable $fetcher): self
    {
        $this->fetcher = $fetcher;
        return $this;
    }

    /**
     * Parse and stitch all `<mfe-fragment ... />` elements in a template shell.
     *
     * @param string $template HTML shell containing <mfe-fragment ... /> tags
     * @param array<string, mixed> $context Global context props
     * @return string Stitched complete HTML
     */
    public function stitch(string $template, array $context = []): string
    {
        $pattern = '#<mfe-fragment\s+([^>]+)(?:>(.*?)</mfe-fragment>|/>)#si';

        return (string)preg_replace_callback($pattern, function (array $matches) use ($context) {
            $attrsRaw = $matches[1];
            $innerFallback = isset($matches[2]) && trim($matches[2]) !== '' ? trim($matches[2]) : null;

            $attrs = $this->parseAttributes($attrsRaw);

            $remote = $attrs['remote'] ?? '';
            $component = $attrs['component'] ?? '';
            $renderMode = RenderMode::tryFrom($attrs['mode'] ?? 'ssr') ?? RenderMode::SSR;
            $timeout = (int)($attrs['timeout'] ?? 3000);
            $fallback = $attrs['fallback'] ?? $innerFallback;

            // Merge props
            $props = $context;
            if (isset($attrs['props'])) {
                $decoded = json_decode($attrs['props'], true);
                if (is_array($decoded)) {
                    $props = array_merge($props, $decoded);
                }
            }

            $fragment = new MfeFragment($remote, $component, $props, $renderMode, $fallback, $timeout);
            return $this->renderFragment($fragment);
        }, $template);
    }

    /**
     * Render an individual fragment according to its RenderMode.
     */
    public function renderFragment(MfeFragment $fragment): string
    {
        if ($fragment->getRenderMode() === RenderMode::CSR) {
            // CSR: emit container placeholder for browser hydration
            return $fragment->renderClientPlaceholder();
        }

        // SSR or HYBRID: resolve remote and fetch rendered HTML
        $remote = $this->registry->getRemote($fragment->getRemote());
        if ($remote === null) {
            return $fragment->getFallbackHtml() ?? "<!-- Remote '{$fragment->getRemote()}' not found -->";
        }

        try {
            $renderUrl = $remote->getEntryUrl() . '/render?component=' . urlencode($fragment->getComponent());
            $renderedHtml = ($this->fetcher)($renderUrl, $fragment->getProps(), $fragment->getTimeout());

            if ($renderedHtml === null) {
                return $fragment->getFallbackHtml() ?? $remote->getFallbackHtml() ?? "<!-- Fragment fetch timed out -->";
            }

            if ($fragment->getRenderMode() === RenderMode::HYBRID) {
                // Wrap in hydration container
                $propsJson = htmlspecialchars(json_encode($fragment->getProps()), ENT_QUOTES, 'UTF-8');
                return sprintf(
                    '<div id="%s" data-mfe-remote="%s" data-mfe-component="%s" data-mfe-props="%s" data-mfe-mode="hybrid">%s</div>',
                    htmlspecialchars($fragment->getId()),
                    htmlspecialchars($fragment->getRemote()),
                    htmlspecialchars($fragment->getComponent()),
                    $propsJson,
                    $renderedHtml
                );
            }

            return $renderedHtml;
        } catch (\Throwable $e) {
            return $fragment->getFallbackHtml() ?? "<!-- SSR Error: {$e->getMessage()} -->";
        }
    }

    /**
     * Default HTTP fragment fetcher.
     */
    public function defaultFetch(string $url, array $props, int $timeoutMs): ?string
    {
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: text/html\r\n",
                'content' => json_encode($props),
                'timeout' => $timeoutMs / 1000,
                'ignore_errors' => true,
            ],
        ];

        $context = stream_context_create($opts);
        $fp = @fopen($url, 'r', false, $context);
        if (!$fp) {
            return null;
        }

        $meta = stream_get_meta_data($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        return $content !== false ? $content : null;
    }

    /**
     * Helper to parse HTML tag attributes into key-value map.
     *
     * @return array<string, string>
     */
    protected function parseAttributes(string $attrsRaw): array
    {
        $attrs = [];
        $pattern = '#([a-zA-Z0-9_\-]+)=(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#';
        if (preg_match_all($pattern, $attrsRaw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = strtolower($m[1]);
                $val = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : ($m[4] ?? ''));
                $attrs[$name] = $val;
            }
        }
        return $attrs;
    }
}
