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

namespace NetFusion\Core\Graph\Reasoner;

use NetFusion\Core\Graph\Models\Triple;

class InferenceRule
{
    public const TYPE_TRANSITIVE = 'transitive';
    public const TYPE_SYMMETRIC  = 'symmetric';
    public const TYPE_INVERSE    = 'inverse';
    public const TYPE_SUBCLASS   = 'subclass';
    public const TYPE_CUSTOM     = 'custom';

    /**
     * @param string $type
     * @param string $predicate1
     * @param string|null $predicate2
     * @param callable|null $customHandler
     */
    public function __construct(
        public readonly string $type,
        public readonly string $predicate1 = '',
        public readonly ?string $predicate2 = null,
        public readonly mixed $customHandler = null
    ) {
    }

    public static function transitive(string $predicate): self
    {
        return new self(self::TYPE_TRANSITIVE, $predicate);
    }

    public static function symmetric(string $predicate): self
    {
        return new self(self::TYPE_SYMMETRIC, $predicate);
    }

    public static function inverse(string $predicate1, string $predicate2): self
    {
        return new self(self::TYPE_INVERSE, $predicate1, $predicate2);
    }

    public static function subClass(): self
    {
        return new self(self::TYPE_SUBCLASS, 'rdfs:subClassOf');
    }

    public static function custom(callable $handler): self
    {
        return new self(self::TYPE_CUSTOM, '', null, $handler);
    }

    /**
     * Apply rule against triple store and return newly deduced triples.
     *
     * @param RDFTripleStore $store
     * @return array<Triple>
     */
    public function apply(RDFTripleStore $store): array
    {
        $newTriples = [];

        if ($this->type === self::TYPE_SYMMETRIC) {
            $existing = $store->query(predicate: $this->predicate1);
            foreach ($existing as $t) {
                $s = $t->getSubject();
                $o = $t->getObject();
                if ($s !== $o && !$store->has($o, $this->predicate1, $s)) {
                    $newTriples[] = Triple::create($o, $this->predicate1, $s, inferred: true);
                }
            }
        } elseif ($this->type === self::TYPE_INVERSE) {
            $existing = $store->query(predicate: $this->predicate1);
            foreach ($existing as $t) {
                $s = $t->getSubject();
                $o = $t->getObject();
                if (!$store->has($o, $this->predicate2, $s)) {
                    $newTriples[] = Triple::create($o, $this->predicate2, $s, inferred: true);
                }
            }
        } elseif ($this->type === self::TYPE_TRANSITIVE) {
            $existing = $store->query(predicate: $this->predicate1);
            foreach ($existing as $t1) {
                $a = $t1->getSubject();
                $b = $t1->getObject();

                $secondHops = $store->query(subject: $b, predicate: $this->predicate1);
                foreach ($secondHops as $t2) {
                    $c = $t2->getObject();
                    if ($a !== $c && !$store->has($a, $this->predicate1, $c)) {
                        $newTriples[] = Triple::create($a, $this->predicate1, $c, inferred: true);
                    }
                }
            }
        } elseif ($this->type === self::TYPE_SUBCLASS) {
            $subClassTriples = $store->query(predicate: 'rdfs:subClassOf');
            foreach ($subClassTriples as $subRel) {
                $subClass = $subRel->getSubject();
                $superClass = $subRel->getObject();

                // Find instances of subClass e.g. (inst, rdf:type, subClass)
                $instances = $store->query(predicate: 'rdf:type', object: $subClass);
                foreach ($instances as $inst) {
                    $indiv = $inst->getSubject();
                    if (!$store->has($indiv, 'rdf:type', $superClass)) {
                        $newTriples[] = Triple::create($indiv, 'rdf:type', $superClass, inferred: true);
                    }
                }
            }
        } elseif ($this->type === self::TYPE_CUSTOM && is_callable($this->customHandler)) {
            $customNew = ($this->customHandler)($store);
            if (is_array($customNew)) {
                $newTriples = array_merge($newTriples, $customNew);
            }
        }

        return $newTriples;
    }
}
