<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Telemetry\Http;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Telemetry\TelemetryManager;

class PrometheusMetricsController
{
    public function __construct(
        protected ?TelemetryManager $telemetry = null
    ) {
        $this->telemetry = $telemetry ?? (function_exists('app') && app()->has('telemetry') ? app('telemetry') : new TelemetryManager());
    }

    public function handle(?Request $request = null): Response
    {
        $output = $this->telemetry->renderPrometheus();
        $contentType = $this->telemetry->exporter('prometheus')->getContentType();

        return new Response($output, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function index(?Request $request = null): Response
    {
        return $this->handle($request);
    }

    public function __invoke(?Request $request = null): Response
    {
        return $this->handle($request);
    }
}
