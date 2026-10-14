<?php
/**
 * NetFusion Framework - Enterprise License Fabric
 *
 * Middleware guarding routes against unauthorized, unlicensed, or expired usage.
 *
 * @package    NetFusion\Core\Http\Middleware
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Http\Middleware;

use NetFusion\Core\Foundation\License\LicenseManager;

class LicenseGuardMiddleware
{
    protected LicenseManager $license;

    public function __construct(?LicenseManager $license = null)
    {
        $this->license = $license ?? new LicenseManager();
    }

    /**
     * Handle incoming request and verify active DotNetBD license.
     *
     * @param mixed    $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        $config = [];
        $configFile = dirname(__DIR__, 3) . '/config/license.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        }

        // Only enforce if explicitly configured
        if (!empty($config['enforce'])) {
            $validation = $this->license->validate();
            if (empty($validation['valid'])) {
                $errorMsg = $validation['message'] ?? 'Software license is inactive or expired. Please activate your DotNetBD license.';

                if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'error'   => 'LICENSE_REQUIRED',
                        'message' => $errorMsg,
                    ]);
                    exit;
                }

                http_response_code(403);
                echo "<h1>403 Forbidden - DotNetBD License Required</h1>";
                echo "<p>" . htmlspecialchars($errorMsg) . "</p>";
                echo "<p>Run <code>php netfusion license:activate &lt;LICENSE_KEY&gt; &lt;COMPANY_NAME&gt;</code> in your terminal to activate.</p>";
                exit;
            }
        }

        return $next($request);
    }
}
