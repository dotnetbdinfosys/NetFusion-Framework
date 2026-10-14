<?php
/**
 * NetFusion Framework - Phase 115 Unit Test Suite
 *
 * Synthetic Biology, BioCAD, CRISPR Screening & Gene Circuit Simulator Verification.
 *
 * @package    NetFusion\Tests
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Bio/Enums/CasVariant.php';
require_once __DIR__ . '/../core/Bio/Enums/OrganismHost.php';
require_once __DIR__ . '/../core/Bio/Enums/GeneticPartType.php';
require_once __DIR__ . '/../core/Bio/Exceptions/BioCadException.php';
require_once __DIR__ . '/../core/Bio/Exceptions/InvalidSequenceException.php';
require_once __DIR__ . '/../core/Bio/Exceptions/GeneticCircuitException.php';
require_once __DIR__ . '/../core/Bio/Sequence/DnaSequence.php';
require_once __DIR__ . '/../core/Bio/Crispr/CrisprEngine.php';
require_once __DIR__ . '/../core/Bio/Codon/CodonOptimizer.php';
require_once __DIR__ . '/../core/Bio/Circuit/GeneticPart.php';
require_once __DIR__ . '/../core/Bio/Circuit/GeneticCircuitSimulator.php';
require_once __DIR__ . '/../core/Bio/BioCadManager.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Bio\BioCadManager;
use NetFusion\Core\Bio\Circuit\GeneticCircuitSimulator;
use NetFusion\Core\Bio\Circuit\GeneticPart;
use NetFusion\Core\Bio\Codon\CodonOptimizer;
use NetFusion\Core\Bio\Crispr\CrisprEngine;
use NetFusion\Core\Bio\Enums\CasVariant;
use NetFusion\Core\Bio\Enums\GeneticPartType;
use NetFusion\Core\Bio\Enums\OrganismHost;
use NetFusion\Core\Bio\Exceptions\InvalidSequenceException;
use NetFusion\Core\Bio\Sequence\DnaSequence;

$testsPassed = 0;
$totalTests  = 10;

function assertTest(bool $condition, string $testName): void {
    global $testsPassed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$testName}\n";
    }
}

echo "=== Running Phase 115: Synthetic Biology & BioCAD Verification ===\n";

// Test 1: DnaSequence validation, reverse complement, transcription, translation
try {
    $dna = new DnaSequence('atgcccaactgggactaa'); // ATG CCC AAC TGG GAC TAA -> M P N W D *
    $revComp = $dna->reverseComplement()->getSequence();
    $rna = $dna->transcribe();
    $protein = $dna->translate();
    $gc = $dna->gcContent();

    $invalidCaught = false;
    try {
        new DnaSequence('ATGC123');
    } catch (InvalidSequenceException $e) {
        $invalidCaught = true;
    }

    assertTest(
        $revComp === 'TTAGTCCCAGTTGGGCAT' &&
        $rna === 'AUGCCCAACUGGGACUAA' &&
        $protein === 'MPNWD*' &&
        abs($gc - 50.0) < 0.1 &&
        $invalidCaught,
        "Test 1: DnaSequence validation, reverse complement, transcription and translation"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n";
}

// Test 2: CasVariant properties & PAM patterns
try {
    $sp = CasVariant::SpCas9;
    $sa = CasVariant::SaCas9;
    $c12 = CasVariant::Cas12a_AsCpf1;

    assertTest(
        $sp->spacerLength() === 20 &&
        $sp->isPamAtThreePrime() === true &&
        $c12->spacerLength() === 23 &&
        $c12->isPamAtThreePrime() === false &&
        $sa->spacerLength() === 21,
        "Test 2: CasVariant specifications, spacer lengths and PAM orientations"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n";
}

// Test 3: CrisprEngine gRNA candidate design and scoring
try {
    $engine = new CrisprEngine();
    // 35-mer with NGG PAM at positions
    $targetSeq = new DnaSequence('AATTTCCCGGGAAATTTCCCGGGAAATTTCCCGGGAGG');
    $guides = $engine->designGuideRnas($targetSeq, CasVariant::SpCas9);

    assertTest(
        !empty($guides) &&
        isset($guides[0]['spacer'], $guides[0]['pam'], $guides[0]['on_target_score']) &&
        strlen($guides[0]['spacer']) === 20 &&
        $guides[0]['on_target_score'] > 0.0,
        "Test 3: CrisprEngine gRNA design and on-target scoring"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n";
}

// Test 4: CrisprEngine MIT Off-Target cleavage scoring
try {
    $engine = new CrisprEngine();
    $spacer = 'ATGCGATCGATCGATCGATC';
    $exactScore = $engine->calculateOffTargetScore($spacer, $spacer);
    // 1 mismatch in non-seed (pos 1)
    $mut1 = 'TTGCGATCGATCGATCGATC';
    $score1 = $engine->calculateOffTargetScore($spacer, $mut1);
    // 1 mismatch in seed region (pos 18)
    $mutSeed = 'ATGCGATCGATCGATCGAAC';
    $scoreSeed = $engine->calculateOffTargetScore($spacer, $mutSeed);
    // 5 mismatches
    $mut5 = 'CCGCGATCGAACGAGCCATC';
    $score5 = $engine->calculateOffTargetScore($spacer, $mut5);

    assertTest(
        $exactScore === 100.0 &&
        $score1 > 0.0 &&
        $scoreSeed < $score1 &&
        $score5 === 0.0,
        "Test 4: CrisprEngine MIT off-target cleavage probability calculation"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 4: " . $e->getMessage() . "\n";
}

// Test 5: OrganismHost attributes and temperatures
try {
    $ecoli = OrganismHost::E_COLI;
    $yeast = OrganismHost::S_CEREVISIAE;
    $human = OrganismHost::H_SAPIENS;

    assertTest(
        $ecoli->optimalTemperature() === 37.0 &&
        $yeast->optimalTemperature() === 30.0 &&
        $human->optimalTemperature() === 37.0 &&
        $ecoli->value === 'e_coli',
        "Test 5: OrganismHost parameters and cultivation specifications"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 5: " . $e->getMessage() . "\n";
}

// Test 6: CodonOptimizer CAI calculation & synonymous optimization
try {
    $opt = new CodonOptimizer();
    // CDS with rare codons in E. coli: CTA (Leu, 0.01 weight), CCC (Pro, 0.01 weight), CGA (Arg, 0.01 weight)
    $rareCds = new DnaSequence('ATGCTACCCCGA');
    $res = $opt->optimize($rareCds, OrganismHost::E_COLI);

    assertTest(
        $res['original_cai'] < $res['optimized_cai'] &&
        $res['substitutions'] > 0 &&
        $res['optimized']->translate() === $rareCds->translate(),
        "Test 6: CodonOptimizer CAI maximization with preserved amino acid translation"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 6: " . $e->getMessage() . "\n";
}

// Test 7: GeneticCircuitSimulator RK4 integration & Toggle Switch
try {
    $sim = new GeneticCircuitSimulator();
    $traj = $sim->simulateToggleSwitch(2.0, 0.2, 10.0, 10.0, 2.0, 2.0, 5.0, 0.1);

    $last = end($traj);
    $uFinal = $last['state']['u'];
    $vFinal = $last['state']['v'];

    assertTest(
        count($traj) > 40 &&
        $uFinal > $vFinal && // High U, Low V bistable state preserved
        $vFinal >= 0.0,
        "Test 7: GeneticCircuitSimulator RK4 Toggle Switch bistability"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 7: " . $e->getMessage() . "\n";
}

// Test 8: GeneticCircuitSimulator Repressilator cyclic oscillation
try {
    $sim = new GeneticCircuitSimulator();
    $traj = $sim->simulateRepressilator(50.0, 0.2, 2.0, 10.0, 0.2);

    $allPositive = true;
    foreach ($traj as $pt) {
        if ($pt['state']['p1'] < 0 || $pt['state']['p2'] < 0 || $pt['state']['p3'] < 0) {
            $allPositive = false;
            break;
        }
    }

    assertTest(
        count($traj) > 45 &&
        $allPositive &&
        isset($traj[10]['state']['p1'], $traj[10]['state']['p2'], $traj[10]['state']['p3']),
        "Test 8: GeneticCircuitSimulator 3-gene Repressilator cyclic dynamics"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 8: " . $e->getMessage() . "\n";
}

// Test 9: GeneticPart & BioCadManager parts registry
try {
    $mgr = new BioCadManager();
    $promoterSeq = new DnaSequence('TTGACAGCTAGCTCAGTCCTAGGTATAATGCTAGC');
    $part = new GeneticPart('BBa_J23100', GeneticPartType::PROMOTER, $promoterSeq, ['efficiency' => 1.0]);

    $mgr->registerPart($part);
    $retrieved = $mgr->getPart('BBa_J23100');
    $arr = $part->toArray();

    assertTest(
        $retrieved !== null &&
        $retrieved->getPartId() === 'BBa_J23100' &&
        $retrieved->getType() === GeneticPartType::PROMOTER &&
        $arr['length'] === 35 &&
        count($mgr->getParts()) === 1,
        "Test 9: GeneticPart BioBrick specification and BioCadManager registry"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 9: " . $e->getMessage() . "\n";
}

// Test 10: Global BioCAD helpers
try {
    $manager = biocad();
    $guides = biocad_crispr('ATGCGATCGATCGATCGATCGATCGATCGATCGGATCGATCGATCGT');
    $opt = biocad_optimize('ATGCTACCC');
    $circuit = biocad_circuit(2.0, 0.2);

    assertTest(
        $manager instanceof BioCadManager &&
        is_array($guides) &&
        isset($opt['optimized']) &&
        is_array($circuit) &&
        count($circuit) > 10,
        "Test 10: Global helpers biocad(), biocad_crispr(), biocad_optimize(), biocad_circuit()"
    );
} catch (\Throwable $e) {
    echo "  [FAIL] Test 10: " . $e->getMessage() . "\n";
}

echo "=== Phase 115 Result: {$testsPassed}/{$totalTests} Tests Passed ===\n";
exit($testsPassed === $totalTests ? 0 : 1);
