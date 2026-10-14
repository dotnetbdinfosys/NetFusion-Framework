<?php
/**
 * NetFusion Framework - Enterprise Cognitive Architecture
 *
 * Conflict resolution and Recognize-Act cognitive cycle rule execution engine.
 *
 * @package    NetFusion\Core\Cognitive\Production
 * @author     DotNetBD <developer@dotnetbd.com>
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Cognitive\Production;

use NetFusion\Core\Cognitive\Enums\ImpasseType;
use NetFusion\Core\Cognitive\Exceptions\CognitiveImpasseException;
use NetFusion\Core\Cognitive\Memory\DeclarativeMemory;
use NetFusion\Core\Cognitive\Memory\EpisodicMemory;

class RuleEngine
{
    /**
     * @var array<string, ProductionRule>
     */
    private array $rules = [];

    public function addRule(ProductionRule $rule): void
    {
        $this->rules[$rule->getName()] = $rule;
    }

    public function getRule(string $name): ?ProductionRule
    {
        return $this->rules[$name] ?? null;
    }

    public function count(): int
    {
        return count($this->rules);
    }

    /**
     * Run a single cognitive Recognize-Act step.
     *
     * 1. Match: find all production rules whose conditions match current buffer states.
     * 2. Conflict Resolution: select the rule with highest utility. If empty, raise Impasse (NoChange).
     * 3. Act: fire selected rule.
     *
     * @param array<string, array<string, mixed>> $buffers
     * @return array{fired_rule: string, utility: float, result: mixed}
     * @throws CognitiveImpasseException
     */
    public function step(array &$buffers, DeclarativeMemory $dm, EpisodicMemory $em): array
    {
        /** @var ProductionRule[] $matchingRules */
        $matchingRules = [];

        foreach ($this->rules as $rule) {
            if ($rule->matches($buffers)) {
                $matchingRules[] = $rule;
            }
        }

        if (empty($matchingRules)) {
            throw new CognitiveImpasseException('Cognitive Impasse: No production rule matched current buffer contents (Impasse: ' . ImpasseType::NoChange->value . ').');
        }

        // Conflict resolution: sort by utility descending
        usort($matchingRules, function (ProductionRule $a, ProductionRule $b) {
            return $b->getUtility() <=> $a->getUtility();
        });

        $chosenRule = $matchingRules[0];
        $result = $chosenRule->fire($buffers, $dm, $em);

        return [
            'fired_rule' => $chosenRule->getName(),
            'utility'    => $chosenRule->getUtility(),
            'result'     => $result,
        ];
    }
}
