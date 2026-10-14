<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Collaboration\OT;

use InvalidArgumentException;

/**
 * Class OperationalTransform
 *
 * Implements the standard Operational Transformation (OT) transformation matrix.
 * Given concurrent operations op1 and op2 originating from the same base document state,
 * transforms them into (op1', op2') such that applying op1 then op2' yields the exact same
 * document state as applying op2 then op1' (TP1 convergence property).
 */
class OperationalTransform
{
    /**
     * Transform op1 and op2 against each other.
     *
     * @param TextOperation $op1 First operation
     * @param TextOperation $op2 Second operation
     * @param string $priority 'left' gives priority to op1 for concurrent insertions at the same index
     * @return array{0: TextOperation, 1: TextOperation} [op1Prime, op2Prime]
     */
    public static function transform(TextOperation $op1, TextOperation $op2, string $priority = 'left'): array
    {
        if ($op1->getBaseLength() !== $op2->getBaseLength()) {
            throw new InvalidArgumentException(
                "Cannot transform operations: op1 base length ({$op1->getBaseLength()}) does not match op2 base length ({$op2->getBaseLength()})"
            );
        }

        $op1Prime = new TextOperation();
        $op2Prime = new TextOperation();

        $ops1 = $op1->getOps();
        $ops2 = $op2->getOps();

        $idx1 = 0;
        $idx2 = 0;
        $curr1 = $ops1[$idx1++] ?? null;
        $curr2 = $ops2[$idx2++] ?? null;

        while ($curr1 !== null || $curr2 !== null) {
            // Case 1: op1 inserts
            if (is_string($curr1)) {
                $op1Prime->insert($curr1);
                $op2Prime->retain(mb_strlen($curr1));
                $curr1 = $ops1[$idx1++] ?? null;
                continue;
            }

            // Case 2: op2 inserts
            if (is_string($curr2)) {
                $op1Prime->retain(mb_strlen($curr2));
                $op2Prime->insert($curr2);
                $curr2 = $ops2[$idx2++] ?? null;
                continue;
            }

            if ($curr1 === null || $curr2 === null) {
                break;
            }

            // Both operations are retain or delete
            if (is_int($curr1) && $curr1 > 0 && is_int($curr2) && $curr2 > 0) {
                // Retain / Retain
                $minRetain = min($curr1, $curr2);
                $op1Prime->retain($minRetain);
                $op2Prime->retain($minRetain);

                if ($curr1 > $curr2) {
                    $curr1 -= $curr2;
                    $curr2 = $ops2[$idx2++] ?? null;
                } elseif ($curr1 === $curr2) {
                    $curr1 = $ops1[$idx1++] ?? null;
                    $curr2 = $ops2[$idx2++] ?? null;
                } else {
                    $curr2 -= $curr1;
                    $curr1 = $ops1[$idx1++] ?? null;
                }
            } elseif (is_int($curr1) && $curr1 < 0 && is_int($curr2) && $curr2 < 0) {
                // Delete / Delete
                $del1 = abs($curr1);
                $del2 = abs($curr2);

                if ($del1 > $del2) {
                    $curr1 = -($del1 - $del2);
                    $curr2 = $ops2[$idx2++] ?? null;
                } elseif ($del1 === $del2) {
                    $curr1 = $ops1[$idx1++] ?? null;
                    $curr2 = $ops2[$idx2++] ?? null;
                } else {
                    $curr2 = -($del2 - $del1);
                    $curr1 = $ops1[$idx1++] ?? null;
                }
            } elseif (is_int($curr1) && $curr1 < 0 && is_int($curr2) && $curr2 > 0) {
                // Delete / Retain
                $del1 = abs($curr1);
                $ret2 = $curr2;

                if ($del1 > $ret2) {
                    $op1Prime->delete($ret2);
                    $curr1 = -($del1 - $ret2);
                    $curr2 = $ops2[$idx2++] ?? null;
                } elseif ($del1 === $ret2) {
                    $op1Prime->delete($del1);
                    $curr1 = $ops1[$idx1++] ?? null;
                    $curr2 = $ops2[$idx2++] ?? null;
                } else {
                    $op1Prime->delete($del1);
                    $curr2 = $ret2 - $del1;
                    $curr1 = $ops1[$idx1++] ?? null;
                }
            } elseif (is_int($curr1) && $curr1 > 0 && is_int($curr2) && $curr2 < 0) {
                // Retain / Delete
                $ret1 = $curr1;
                $del2 = abs($curr2);

                if ($ret1 > $del2) {
                    $op2Prime->delete($del2);
                    $curr1 = $ret1 - $del2;
                    $curr2 = $ops2[$idx2++] ?? null;
                } elseif ($ret1 === $del2) {
                    $op2Prime->delete($del2);
                    $curr1 = $ops1[$idx1++] ?? null;
                    $curr2 = $ops2[$idx2++] ?? null;
                } else {
                    $op2Prime->delete($ret1);
                    $curr2 = -($del2 - $ret1);
                    $curr1 = $ops1[$idx1++] ?? null;
                }
            }
        }

        return [$op1Prime, $op2Prime];
    }
}
