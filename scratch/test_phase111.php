<?php
/**
 * NetFusion Framework - Phase 111 Comprehensive Verification Suite
 *
 * Test suite for Topological Data Analysis (TDA), Persistent Homology & Vietoris-Rips complexes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Analytics\Tda\Enums\DistanceMetric;
use NetFusion\Core\Analytics\Tda\Enums\HomologyDimension;
use NetFusion\Core\Analytics\Tda\Exceptions\DegenerateSimplexException;
use NetFusion\Core\Analytics\Tda\Exceptions\TdaComputationException;
use NetFusion\Core\Analytics\Tda\Homology\BoundaryMatrix;
use NetFusion\Core\Analytics\Tda\Homology\PersistenceInterval;
use NetFusion\Core\Analytics\Tda\Simplicial\Simplex;
use NetFusion\Core\Analytics\Tda\Simplicial\VietorisRipsComplex;
use NetFusion\Core\Analytics\Tda\TdaManager;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$message}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failed++;
    }
}

echo "=== Running Phase 111: Topological Data Analysis (TDA) Verification Suite ===\n\n";

// Test 1: DistanceMetric enum calculations
try {
    $euc = DistanceMetric::Euclidean->calculate([0.0, 0.0], [3.0, 4.0]); // 5.0
    $man = DistanceMetric::Manhattan->calculate([0.0, 0.0], [3.0, 4.0]); // 7.0
    $cos = DistanceMetric::Cosine->calculate([1.0, 0.0], [0.0, 1.0]);    // Orthogonal = 1.0 - 0 = 1.0

    assertTest(
        abs($euc - 5.0) < 1e-6 && abs($man - 7.0) < 1e-6 && abs($cos - 1.0) < 1e-6,
        'Test 1: DistanceMetric enum computes accurate Euclidean (5.0), Manhattan (7.0), and Cosine (1.0) metrics'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 1 Exception: ' . $e->getMessage());
}

// Test 2: Simplex structure, sorting, and boundary operator
try {
    $triangle = new Simplex([2, 0, 1], 1.5);
    $verts = $triangle->getVertices();
    $dim = $triangle->getDimension();
    $faces = $triangle->getBoundaryFaces();

    // Vertices should be sorted: [0, 1, 2], dim = 2, 3 boundary faces
    assertTest(
        $verts === [0, 1, 2] && $dim === 2 && count($faces) === 3 &&
        $faces[0]['vertices'] === [1, 2] && $faces[1]['vertices'] === [0, 2] && $faces[2]['vertices'] === [0, 1],
        'Test 2: Simplex canonicalizes vertex ordering, determines dimension (dim=2), and generates oriented codimension-1 boundary faces'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 2 Exception: ' . $e->getMessage());
}

// Test 3: VietorisRipsComplex filtered simplicial construction
try {
    // Equilateral triangle of side length 1.0
    $points = [
        [0.0, 0.0],
        [1.0, 0.0],
        [0.5, 0.866025],
    ];
    $complex = new VietorisRipsComplex($points, 1.2, 2);
    $simplices = $complex->getSimplices();

    // At eps=1.2, should contain 3 vertices, 3 edges (all dist 1.0), and 1 triangle (diam 1.0) = 7 simplices
    assertTest(
        $complex->getNumPoints() === 3 && count($simplices) === 7,
        'Test 3: VietorisRipsComplex builds filtered simplicial complex with all 0-simplices, 1-simplices, and 2-simplices'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 3 Exception: ' . $e->getMessage());
}

// Test 4: PersistenceInterval feature lifespan and alive checking
try {
    $finite = new PersistenceInterval(HomologyDimension::H0, 0.0, 1.5);
    $infinite = new PersistenceInterval(HomologyDimension::H1, 0.8, null);

    assertTest(
        $finite->getLifespan() === 1.5 && $finite->isAliveAt(1.0) && !$finite->isAliveAt(1.6) &&
        $infinite->isInfinite() && $infinite->isAliveAt(100.0),
        'Test 4: PersistenceInterval models finite topological lifespan and infinite persistence features'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 4 Exception: ' . $e->getMessage());
}

// Test 5: BoundaryMatrix Z_2 column reduction on disconnected components (H0)
try {
    // Two isolated clusters at distance 5.0
    $points = [
        [0.0, 0.0], [0.1, 0.0], // Cluster 1
        [5.0, 0.0], [5.1, 0.0], // Cluster 2
    ];
    $complex = new VietorisRipsComplex($points, 1.0, 1);
    $bm = new BoundaryMatrix($complex);

    // At eps=0.5, the points within each cluster merge, leaving 2 connected components (beta_0 = 2)
    $b0 = $bm->getBettiNumber(HomologyDimension::H0, 0.5);

    assertTest(
        $b0 === 2,
        'Test 5: BoundaryMatrix Z_2 column reduction resolves persistent connected components (β_0 = 2) across clusters'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 5 Exception: ' . $e->getMessage());
}

// Test 6: Topological Loop (H1) detection on circle manifold
try {
    // 6-point ring of radius 1.0
    $points = [];
    for ($i = 0; $i < 6; $i++) {
        $angle = $i * (2.0 * M_PI / 6.0);
        $points[] = [cos($angle), sin($angle)];
    }

    $complex = new VietorisRipsComplex($points, 1.8, 2);
    $bm = new BoundaryMatrix($complex);
    $h1Intervals = $bm->getIntervalsByDimension(HomologyDimension::H1);

    // At edge connection distance (dist ~ 1.0), the 1D circular loop is born and persists until triangle filling
    assertTest(
        count($h1Intervals) >= 1 && $h1Intervals[0]->getLifespan() > 0.0,
        'Test 6: Persistent homology successfully detects non-trivial topological 1D circular loop (H1 cycle) with positive lifespan'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 6 Exception: ' . $e->getMessage());
}

// Test 7: Betti curve evaluation across filtration steps
try {
    $points = [
        [0.0, 0.0], [1.0, 0.0], [0.0, 1.0], [1.0, 1.0],
    ];
    $mgr = new TdaManager();
    $curve = $mgr->computeBettiCurve($points, [0.0, 0.5, 1.1, 1.6]);

    // At eps=0.0: 4 components. At eps=1.1: edges form (1 component).
    assertTest(
        count($curve) === 4 && $curve[0]['betti_0'] === 4 && $curve[2]['betti_0'] === 1,
        'Test 7: TdaManager computes dynamic Betti curve β_0(ε) correctly transitioning from 4 components to 1'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 7 Exception: ' . $e->getMessage());
}

// Test 8: TdaManager facade computePersistentHomology
try {
    $points = [
        [0.0, 0.0], [1.0, 0.0], [0.5, 0.866],
    ];
    $mgr = new TdaManager();
    $res = $mgr->computePersistentHomology($points, 1.5, 2);

    assertTest(
        $res['num_points'] === 3 && $res['num_simplices'] === 7 && isset($res['betti_numbers']['h0']),
        'Test 8: TdaManager facade orchestrates point cloud ingestion, simplicial complex synthesis, and barcode reporting'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 8 Exception: ' . $e->getMessage());
}

// Test 9: Global helper functions (tda, tda_rips, tda_homology, tda_betti)
try {
    $pts = [[0.0, 0.0], [2.0, 2.0]];
    $mgr = tda();
    $cmplx = tda_rips($pts, 3.0);
    $hom = tda_homology($pts, 3.0);

    assertTest(
        $mgr instanceof TdaManager && $cmplx instanceof VietorisRipsComplex && count($hom['intervals']) > 0,
        'Test 9: Global helper functions tda(), tda_rips(), and tda_homology() operate cleanly with IoC container'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 9 Exception: ' . $e->getMessage());
}

// Test 10: Exception guards on degenerate simplex and dimension mismatch
try {
    $caughtDegenerate = false;
    try {
        new Simplex([1, 2, 1]); // duplicate vertex 1
    } catch (DegenerateSimplexException) {
        $caughtDegenerate = true;
    }

    $caughtDimMismatch = false;
    try {
        DistanceMetric::Euclidean->calculate([1.0, 2.0], [1.0]); // 2D vs 1D
    } catch (TdaComputationException) {
        $caughtDimMismatch = true;
    }

    assertTest(
        $caughtDegenerate && $caughtDimMismatch,
        'Test 10: Robust error handling guards against degenerate simplexes and vector dimension mismatches'
    );
} catch (Throwable $e) {
    assertTest(false, 'Test 10 Exception: ' . $e->getMessage());
}

echo "\nVerification Summary: {$passed} Passed, {$failed} Failed\n";
exit($failed === 0 ? 0 : 1);
