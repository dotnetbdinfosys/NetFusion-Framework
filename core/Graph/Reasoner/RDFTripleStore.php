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

class RDFTripleStore
{
    /**
     * @var array<string, Triple> Key => Triple
     */
    protected array $triples = [];

    /**
     * Subject Index: Subject => [Key => Triple]
     */
    protected array $spo = [];

    /**
     * Predicate Index: Predicate => [Key => Triple]
     */
    protected array $pos = [];

    /**
     * Object Index: Object => [Key => Triple]
     */
    protected array $osp = [];

    public function add(Triple $triple): bool
    {
        $key = $triple->getKey();
        if (isset($this->triples[$key])) {
            return false;
        }

        $s = $triple->getSubject();
        $p = $triple->getPredicate();
        $o = $triple->getObject();

        $this->triples[$key] = $triple;
        $this->spo[$s][$key] = $triple;
        $this->pos[$p][$key] = $triple;
        $this->osp[$o][$key] = $triple;

        return true;
    }

    public function has(string $subject, string $predicate, string $object): bool
    {
        $key = "{$subject}|{$predicate}|{$object}";
        return isset($this->triples[$key]);
    }

    public function remove(string $subject, string $predicate, string $object): bool
    {
        $key = "{$subject}|{$predicate}|{$object}";
        if (!isset($this->triples[$key])) {
            return false;
        }

        unset($this->triples[$key]);
        unset($this->spo[$subject][$key]);
        unset($this->pos[$predicate][$key]);
        unset($this->osp[$object][$key]);

        return true;
    }

    /**
     * Query triples using indices. Wildcard or null matches any.
     *
     * @param string|null $subject
     * @param string|null $predicate
     * @param string|null $object
     * @return array<Triple>
     */
    public function query(?string $subject = null, ?string $predicate = null, ?string $object = null): array
    {
        $sWild = ($subject === null || $subject === '?' || $subject === '*');
        $pWild = ($predicate === null || $predicate === '?' || $predicate === '*');
        $oWild = ($object === null || $object === '?' || $object === '*');

        // All wildcards
        if ($sWild && $pWild && $oWild) {
            return array_values($this->triples);
        }

        // Subject index lookup
        if (!$sWild && isset($this->spo[$subject])) {
            $pool = $this->spo[$subject];
            $results = [];
            foreach ($pool as $t) {
                if ($t->matches($subject, $predicate, $object)) {
                    $results[] = $t;
                }
            }
            return $results;
        }

        // Predicate index lookup
        if (!$pWild && isset($this->pos[$predicate])) {
            $pool = $this->pos[$predicate];
            $results = [];
            foreach ($pool as $t) {
                if ($t->matches($subject, $predicate, $object)) {
                    $results[] = $t;
                }
            }
            return $results;
        }

        // Object index lookup
        if (!$oWild && isset($this->osp[$object])) {
            $pool = $this->osp[$object];
            $results = [];
            foreach ($pool as $t) {
                if ($t->matches($subject, $predicate, $object)) {
                    $results[] = $t;
                }
            }
            return $results;
        }

        return [];
    }

    public function count(): int
    {
        return count($this->triples);
    }

    /**
     * @return array<Triple>
     */
    public function all(): array
    {
        return array_values($this->triples);
    }

    public function clear(): void
    {
        $this->triples = [];
        $this->spo = [];
        $this->pos = [];
        $this->osp = [];
    }
}
