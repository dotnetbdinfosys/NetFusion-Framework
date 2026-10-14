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

namespace NetFusion\Core\CRDT\OT;

use NetFusion\Core\CRDT\Enums\OperationType;

/**
 * Operational Transformation (OT) engine implementing inclusion transformation IT(op1, op2)
 * for concurrent Insert and Delete operations.
 */
class OtTransformer
{
    /**
     * Transform operation $op1 against concurrent operation $op2 such that:
     * apply(apply(doc, op2), IT(op1, op2)) == apply(apply(doc, op1), IT(op2, op1))
     *
     * @return array{0: TextOp, 1: TextOp} [op1Prime, op2Prime]
     */
    public static function transform(TextOp $op1, TextOp $op2, string $tieBreakerPriority = 'left'): array
    {
        $t1 = $op1->getType();
        $t2 = $op2->getType();

        // 1. INSERT vs INSERT
        if ($t1 === OperationType::INSERT && $t2 === OperationType::INSERT) {
            $p1 = $op1->getPosition();
            $p2 = $op2->getPosition();
            $len1 = $op1->getLength();
            $len2 = $op2->getLength();

            if ($p1 < $p2 || ($p1 === $p2 && $tieBreakerPriority === 'left')) {
                // op1 stays at p1; op2 shifts right by len1
                $op1Prime = TextOp::insert($p1, $op1->getText(), $op1->getAttributes());
                $op2Prime = TextOp::insert($p2 + $len1, $op2->getText(), $op2->getAttributes());
            } else {
                // op2 stays at p2; op1 shifts right by len2
                $op1Prime = TextOp::insert($p1 + $len2, $op1->getText(), $op1->getAttributes());
                $op2Prime = TextOp::insert($p2, $op2->getText(), $op2->getAttributes());
            }

            return [$op1Prime, $op2Prime];
        }

        // 2. INSERT vs DELETE
        if ($t1 === OperationType::INSERT && $t2 === OperationType::DELETE) {
            $p1 = $op1->getPosition();
            $p2 = $op2->getPosition();
            $len1 = $op1->getLength();
            $len2 = $op2->getLength();

            if ($p1 <= $p2) {
                // Insert occurs before delete range: delete position shifts right
                $op1Prime = TextOp::insert($p1, $op1->getText(), $op1->getAttributes());
                $op2Prime = TextOp::delete($p2 + $len1, $len2);
            } elseif ($p1 >= $p2 + $len2) {
                // Insert occurs after delete range: insert position shifts left
                $op1Prime = TextOp::insert($p1 - $len2, $op1->getText(), $op1->getAttributes());
                $op2Prime = TextOp::delete($p2, $len2);
            } else {
                // Insert occurs inside the deleted segment: shift insert to delete origin
                $op1Prime = TextOp::insert($p2, $op1->getText(), $op1->getAttributes());
                $op2Prime = TextOp::delete($p2, $len2);
            }

            return [$op1Prime, $op2Prime];
        }

        // 3. DELETE vs INSERT
        if ($t1 === OperationType::DELETE && $t2 === OperationType::INSERT) {
            [$op2P, $op1P] = self::transform($op2, $op1, $tieBreakerPriority === 'left' ? 'right' : 'left');
            return [$op1P, $op2P];
        }

        // 4. DELETE vs DELETE
        if ($t1 === OperationType::DELETE && $t2 === OperationType::DELETE) {
            $p1 = $op1->getPosition();
            $p2 = $op2->getPosition();
            $len1 = $op1->getLength();
            $len2 = $op2->getLength();

            if ($p1 + $len1 <= $p2) {
                // op1 is completely before op2
                $op1Prime = TextOp::delete($p1, $len1);
                $op2Prime = TextOp::delete($p2 - $len1, $len2);
            } elseif ($p2 + $len2 <= $p1) {
                // op2 is completely before op1
                $op1Prime = TextOp::delete($p1 - $len2, $len1);
                $op2Prime = TextOp::delete($p2, $len2);
            } else {
                // Overlapping deletes
                $start = min($p1, $p2);
                $end = max($p1 + $len1, $p2 + $len2);
                $overlapLen = $end - $start;

                $newLen1 = max(0, $len1 - ($overlapLen - $len2));
                $newLen2 = max(0, $len2 - ($overlapLen - $len1));

                $op1Prime = TextOp::delete($start, $newLen1);
                $op2Prime = TextOp::delete($start, $newLen2);
            }

            return [$op1Prime, $op2Prime];
        }

        return [$op1, $op2];
    }

    /**
     * Transform an incoming client operation against a sequence of concurrent committed revisions.
     *
     * @param TextOp[] $historyOps
     */
    public static function transformAgainstHistory(TextOp $clientOp, array $historyOps): TextOp
    {
        $transformed = $clientOp;
        foreach ($historyOps as $histOp) {
            [$transformed, ] = self::transform($transformed, $histOp, 'right');
        }
        return $transformed;
    }
}
