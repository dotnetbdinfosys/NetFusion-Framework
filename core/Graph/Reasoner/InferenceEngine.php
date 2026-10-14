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

use NetFusion\Core\Graph\Contracts\ReasonerInterface;
use NetFusion\Core\Graph\Models\Triple;

class InferenceEngine implements ReasonerInterface
{
    /**
     * @var array<InferenceRule>
     */
    protected array $rules = [];

    public function __construct(
        protected RDFTripleStore $store = new RDFTripleStore(),
        protected int $maxInferenceRounds = 20
    ) {
    }

    public function getStore(): RDFTripleStore
    {
        return $this->store;
    }

    public function addRule(InferenceRule $rule): static
    {
        $this->rules[] = $rule;
        return $this;
    }

    public function addTriple(Triple|string $subject, ?string $predicate = null, ?string $object = null): static
    {
        if ($subject instanceof Triple) {
            $this->store->add($subject);
        } elseif ($predicate !== null && $object !== null) {
            $this->store->add(Triple::create($subject, $predicate, $object));
        }
        return $this;
    }

    public function hasTriple(string $subject, string $predicate, string $object): bool
    {
        return $this->store->has($subject, $predicate, $object);
    }

    public function removeTriple(string $subject, string $predicate, string $object): bool
    {
        return $this->store->remove($subject, $predicate, $object);
    }

    public function queryTriples(?string $subject = null, ?string $predicate = null, ?string $object = null): array
    {
        return $this->store->query($subject, $predicate, $object);
    }

    public function infer(): int
    {
        $totalInferred = 0;

        for ($round = 0; $round < $this->maxInferenceRounds; $round++) {
            $roundInferred = 0;

            foreach ($this->rules as $rule) {
                $deductions = $rule->apply($this->store);
                foreach ($deductions as $triple) {
                    if ($this->store->add($triple)) {
                        $roundInferred++;
                        $totalInferred++;
                    }
                }
            }

            // Fixed-point reached (no new deductions)
            if ($roundInferred === 0) {
                break;
            }
        }

        return $totalInferred;
    }
}
