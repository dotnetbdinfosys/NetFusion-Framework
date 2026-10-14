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
use JsonSerializable;
use NetFusion\Core\Collaboration\Ot\Contracts\OperationInterface;

/**
 * Class TextOperation
 *
 * Encapsulates an atomic or composite Operational Transformation (OT) delta.
 * Composed of atomic actions: retain (int), insert (string), delete (negative int).
 */
class TextOperation implements OperationInterface, JsonSerializable
{
    /** @var array<int, int|string> */
    private array $ops = [];

    private int $baseLength = 0;
    private int $targetLength = 0;

    /**
     * Retain n characters in document without modification.
     */
    public function retain(int $n): self
    {
        if ($n <= 0) {
            return $this;
        }

        $this->baseLength += $n;
        $this->targetLength += $n;

        $lastIdx = count($this->ops) - 1;
        if ($lastIdx >= 0 && is_int($this->ops[$lastIdx]) && $this->ops[$lastIdx] > 0) {
            $this->ops[$lastIdx] += $n;
        } else {
            $this->ops[] = $n;
        }

        return $this;
    }

    /**
     * Insert text string into document at current cursor.
     */
    public function insert(string $str): self
    {
        if ($str === '') {
            return $this;
        }

        $this->targetLength += mb_strlen($str);

        $lastIdx = count($this->ops) - 1;
        if ($lastIdx >= 0 && is_string($this->ops[$lastIdx])) {
            $this->ops[$lastIdx] .= $str;
        } elseif ($lastIdx >= 0 && is_int($this->ops[$lastIdx]) && $this->ops[$lastIdx] < 0) {
            // Normalize: insert before delete if adjacent
            if ($lastIdx > 0 && is_string($this->ops[$lastIdx - 1])) {
                $this->ops[$lastIdx - 1] .= $str;
            } else {
                $del = array_pop($this->ops);
                $this->ops[] = $str;
                $this->ops[] = $del;
            }
        } else {
            $this->ops[] = $str;
        }

        return $this;
    }

    /**
     * Delete n characters from document at current cursor. Represented as negative integer.
     */
    public function delete(int $n): self
    {
        if ($n <= 0) {
            return $this;
        }

        $this->baseLength += $n;

        $lastIdx = count($this->ops) - 1;
        if ($lastIdx >= 0 && is_int($this->ops[$lastIdx]) && $this->ops[$lastIdx] < 0) {
            $this->ops[$lastIdx] -= $n;
        } else {
            $this->ops[] = -$n;
        }

        return $this;
    }

    /**
     * Apply operation directly to document text.
     */
    public function apply(string $doc): string
    {
        $docChars = mb_str_split($doc);
        if (count($docChars) !== $this->baseLength) {
            throw new InvalidArgumentException("Operation base length ({$this->baseLength}) does not match document length (" . count($docChars) . ")");
        }

        $newChars = [];
        $docIdx = 0;

        foreach ($this->ops as $op) {
            if (is_int($op)) {
                if ($op > 0) {
                    // Retain
                    for ($i = 0; $i < $op; $i++) {
                        $newChars[] = $docChars[$docIdx++];
                    }
                } else {
                    // Delete
                    $docIdx += abs($op);
                }
            } elseif (is_string($op)) {
                // Insert
                $insertChars = mb_str_split($op);
                foreach ($insertChars as $c) {
                    $newChars[] = $c;
                }
            }
        }

        return implode('', $newChars);
    }

    /**
     * Compose two operations into a single combined operation: $this then $other.
     */
    public function compose(OperationInterface $other): OperationInterface
    {
        $otherBase = method_exists($other, 'getBaseLength') ? $other->getBaseLength() : (int) ($other->toArray()['baseLength'] ?? 0);
        if ($this->targetLength !== $otherBase) {
            throw new InvalidArgumentException("Cannot compose operations: target length ({$this->targetLength}) does not match base length ({$otherBase})");
        }

        $result = new self();
        $ops1 = $this->ops;
        $ops2 = method_exists($other, 'getOps') ? $other->getOps() : (array) ($other->toArray()['ops'] ?? []);

        $idx1 = 0;
        $idx2 = 0;
        $op1 = $ops1[$idx1++] ?? null;
        $op2 = $ops2[$idx2++] ?? null;

        while ($op1 !== null || $op2 !== null) {
            if (is_int($op1) && $op1 < 0) {
                $result->delete(abs($op1));
                $op1 = $ops1[$idx1++] ?? null;
                continue;
            }

            if (is_string($op2)) {
                $result->insert($op2);
                $op2 = $ops2[$idx2++] ?? null;
                continue;
            }

            if ($op1 === null) {
                throw new InvalidArgumentException("Cannot compose operations: op1 terminated early");
            }
            if ($op2 === null) {
                throw new InvalidArgumentException("Cannot compose operations: op2 terminated early");
            }

            if (is_int($op1) && $op1 > 0 && is_int($op2) && $op2 > 0) {
                if ($op1 > $op2) {
                    $result->retain($op2);
                    $op1 -= $op2;
                    $op2 = $ops2[$idx2++] ?? null;
                } elseif ($op1 === $op2) {
                    $result->retain($op1);
                    $op1 = $ops1[$idx1++] ?? null;
                    $op2 = $ops2[$idx2++] ?? null;
                } else {
                    $result->retain($op1);
                    $op2 -= $op1;
                    $op1 = $ops1[$idx1++] ?? null;
                }
            } elseif (is_string($op1) && is_int($op2) && $op2 < 0) {
                $len1 = mb_strlen($op1);
                $len2 = abs($op2);
                if ($len1 > $len2) {
                    $op1 = mb_substr($op1, $len2);
                    $op2 = $ops2[$idx2++] ?? null;
                } elseif ($len1 === $len2) {
                    $op1 = $ops1[$idx1++] ?? null;
                    $op2 = $ops2[$idx2++] ?? null;
                } else {
                    $op2 = -($len2 - $len1);
                    $op1 = $ops1[$idx1++] ?? null;
                }
            } elseif (is_string($op1) && is_int($op2) && $op2 > 0) {
                $len1 = mb_strlen($op1);
                if ($len1 > $op2) {
                    $result->insert(mb_substr($op1, 0, $op2));
                    $op1 = mb_substr($op1, $op2);
                    $op2 = $ops2[$idx2++] ?? null;
                } elseif ($len1 === $op2) {
                    $result->insert($op1);
                    $op1 = $ops1[$idx1++] ?? null;
                    $op2 = $ops2[$idx2++] ?? null;
                } else {
                    $result->insert($op1);
                    $op2 -= $len1;
                    $op1 = $ops1[$idx1++] ?? null;
                }
            } elseif (is_int($op1) && $op1 > 0 && is_int($op2) && $op2 < 0) {
                $len2 = abs($op2);
                if ($op1 > $len2) {
                    $result->delete($len2);
                    $op1 -= $len2;
                    $op2 = $ops2[$idx2++] ?? null;
                } elseif ($op1 === $len2) {
                    $result->delete($op1);
                    $op1 = $ops1[$idx1++] ?? null;
                    $op2 = $ops2[$idx2++] ?? null;
                } else {
                    $result->delete($op1);
                    $op2 = -($len2 - $op1);
                    $op1 = $ops1[$idx1++] ?? null;
                }
            }
        }

        return $result;
    }

    public function isNoop(): bool
    {
        return empty($this->ops) || (count($this->ops) === 1 && is_int($this->ops[0]) && $this->ops[0] > 0);
    }

    /**
     * @return array<int, int|string>
     */
    public function getOps(): array
    {
        return $this->ops;
    }

    public function getBaseLength(): int
    {
        return $this->baseLength;
    }

    public function getTargetLength(): int
    {
        return $this->targetLength;
    }

    public function toArray(): array
    {
        return [
            'ops' => $this->ops,
            'baseLength' => $this->baseLength,
            'targetLength' => $this->targetLength,
        ];
    }

    public static function fromArray(array $data): static
    {
        $instance = new self();
        $instance->ops = (array) ($data['ops'] ?? []);
        $instance->baseLength = (int) ($data['baseLength'] ?? 0);
        $instance->targetLength = (int) ($data['targetLength'] ?? 0);
        return $instance;
    }

    public function invert(string $document): OperationInterface
    {
        $inverse = new self();
        $docChars = mb_str_split($document);
        $docIdx = 0;

        foreach ($this->ops as $op) {
            if (is_int($op)) {
                if ($op > 0) {
                    $inverse->retain($op);
                    $docIdx += $op;
                } else {
                    $delLen = abs($op);
                    $delChars = array_slice($docChars, $docIdx, $delLen);
                    $inverse->insert(implode('', $delChars));
                    $docIdx += $delLen;
                }
            } elseif (is_string($op)) {
                $insLen = mb_strlen($op);
                $inverse->delete($insLen);
            }
        }

        return $inverse;
    }

    public function transform(OperationInterface $other, string $side = 'left'): array
    {
        return self::transformOperations($this, $other, $side);
    }

    public static function transformOperations(OperationInterface $op1, OperationInterface $op2, string $priority = 'left'): array
    {
        return OperationalTransform::transform($op1, $op2, $priority);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
