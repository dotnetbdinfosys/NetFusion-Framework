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

use NetFusion\Core\ZKP\Math\FiniteField;
use NetFusion\Core\ZKP\Exceptions\ConstraintSatisfactionException;

/**
 * Rank-1 Constraint System (R1CS) arithmetic circuit container.
 */
class R1csCircuit
{
    private string $name;
    private FiniteField $field;
    /** @var array<string, Variable> */
    private array $variables = [];
    /** @var Constraint[] */
    private array $constraints = [];

    public function __construct(string $name = 'circuit', ?FiniteField $field = null)
    {
        $this->name  = $name;
        $this->field = $field ?? FiniteField::bn254();
        // Variable ONE is standard constant 1
        $this->variables[Variable::ONE] = Variable::constantOne();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getField(): FiniteField
    {
        return $this->field;
    }

    public function createVariable(string $name, bool $isPublic = false): Variable
    {
        $var = new Variable($name, $isPublic);
        $this->variables[$name] = $var;
        return $var;
    }

    public function publicVariable(string $name): Variable
    {
        return $this->createVariable($name, true);
    }

    public function privateVariable(string $name): Variable
    {
        return $this->createVariable($name, false);
    }

    public function linearCombination(): LinearCombination
    {
        return new LinearCombination($this->field);
    }

    /**
     * Add R1CS constraint: (A) * (B) = (C)
     */
    public function addConstraint(LinearCombination $a, LinearCombination $b, LinearCombination $c): self
    {
        $this->constraints[] = new Constraint($a, $b, $c, $this->field);
        return $this;
    }

    /**
     * Check if witness vector satisfies all constraints in the R1CS system.
     */
    public function verifyWitness(array $witness): bool
    {
        // Enforce ONE wire = 1
        $witness[Variable::ONE] = $this->field->one();

        foreach ($this->constraints as $i => $constraint) {
            if (!$constraint->isSatisfied($witness)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assert satisfaction or throw ConstraintSatisfactionException.
     */
    public function assertSatisfied(array $witness): void
    {
        // Enforce ONE wire = 1
        $witness[Variable::ONE] = $this->field->one();

        foreach ($this->constraints as $i => $constraint) {
            if (!$constraint->isSatisfied($witness)) {
                throw new ConstraintSatisfactionException("Constraint #{$i} is not satisfied by given witness");
            }
        }
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getPublicVariableNames(): array
    {
        $names = [];
        foreach ($this->variables as $v) {
            if ($v->isPublic() && $v->getName() !== Variable::ONE) {
                $names[] = $v->getName();
            }
        }
        return $names;
    }
}
