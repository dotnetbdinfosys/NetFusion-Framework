<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/RedirectResponse.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Specialized HTTP redirect response with Location header and HTML meta refresh fallback.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/RedirectResponse.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Specialized HTTP redirect response with Location header and HTML meta refresh fallback.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Http/RedirectResponse.php
| Module    : Core\Http
| Namespace : NetFusion\Core\Http
| Purpose   : Specialized HTTP redirect response with Location header and HTML meta refresh fallback.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Http;

/**
 * NetFusion Redirect Response
 *
 * A specialised Response subclass that performs HTTP redirects.
 * Automatically sets the Location header and the correct status code.
 *
 * @package NetFusion\Core\Http
 * @version 1.0.0
 */
class RedirectResponse extends Response
{
    /**
     * The target redirect URL.
     */
    private string $targetUrl;

    /**
     * @param string $url        The URL to redirect to.
     * @param int    $statusCode 301 (permanent) or 302 (temporary, default).
     */
    public function __construct(string $url, int $statusCode = 302)
    {
        $this->targetUrl = $url;

        parent::__construct(
            content   : sprintf('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting…</title>'
                . '<meta http-equiv="refresh" content="0;url=%1$s"></head>'
                . '<body><p>Redirecting to <a href="%1$s">%1$s</a>…</p></body></html>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            ),
            statusCode: $statusCode,
            headers   : ['Location' => $url],
        );
    }

    /**
     * Get the target URL of the redirect.
     */
    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    /**
     * Change the target URL.
     *
     * @param  string $url New URL.
     * @return static
     */
    public function to(string $url): static
    {
        $this->targetUrl = $url;
        return $this->withHeader('Location', $url);
    }

    /**
     * Create a permanent (301) redirect.
     *
     * @param  string $url The target URL.
     * @return static
     */
    public static function permanent(string $url): static
    {
        return new static($url, 301);
    }
}
