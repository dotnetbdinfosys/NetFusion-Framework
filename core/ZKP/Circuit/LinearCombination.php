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

namespace NetFusion\Core\ZKP\Circuit;

use NetFusion\Core\ZKP\Math\FieldElement;
use NetFusion\Core\ZKP\Math\FiniteField;

/**
 * Linear combination of circuit variables: sum(c_i * var_i).
 */
class LinearCombination
{
    /** @var array<string, FieldElement> variableName => coefficient */
    private array $terms = [];
    private FiniteField $field;

    public function __construct(FiniteField $field, array $terms = [])
    {
        $this->field = $field;
        foreach ($terms as $varName => $coeff) {
            $this->terms[(string)$varName] = $coeff instanceof FieldElement ? $coeff : $field->element($coeff);
        }
    }

    public function addTerm(string|Variable $var, FieldElement|int|string $coeff): self
    {
        $varName = $var instanceof Variable ? $var->getName() : (string)$var;
        $coeffElem = $coeff instanceof FieldElement ? $coeff : $this->field->element($coeff);

        if (isset($this->terms[$varName])) {
            $this->terms[$varName] = $this->terms[$varName]->add($coeffElem);
        } else {
            $this->terms[$varName] = $coeffElem;
        }

        if ($this->terms[$varName]->isZero()) {
            unset($this->terms[$varName]);
        }

        return $this;
    }

    public function evaluate(array $witness): FieldElement
    {
        $res = $this->field->zero();
        foreach ($this->terms as $varName => $coeff) {
            $val = $witness[$varName] ?? ($varName === Variable::ONE ? $this->field->one() : $this->field->zero());
            $valElem = $val instanceof FieldElement ? $val : $this->field->element($val);
            $res = $res->add($coeff->mul($valElem));
        }
        return $res;
    }

    /**
     * @return array<string, FieldElement>
     */
    public function getTerms(): array
    {
        return $this->terms;
    }
}
