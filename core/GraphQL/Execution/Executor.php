<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/GraphQL/Execution/Executor.php
| Module    : GraphQL
| Namespace : NetFusion\Core\GraphQL\Execution
| Purpose   : GraphQL Query, Mutation & Introspection Execution Engine.
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

namespace NetFusion\Core\GraphQL\Execution;

require_once __DIR__ . '/../Parser/AST.php';

use NetFusion\Core\GraphQL\Contracts\TypeInterface;
use NetFusion\Core\GraphQL\Parser\ArgumentNode;
use NetFusion\Core\GraphQL\Parser\BooleanValueNode;
use NetFusion\Core\GraphQL\Parser\DocumentNode;
use NetFusion\Core\GraphQL\Parser\EnumValueNode;
use NetFusion\Core\GraphQL\Parser\FieldNode;
use NetFusion\Core\GraphQL\Parser\FloatValueNode;
use NetFusion\Core\GraphQL\Parser\FragmentDefinitionNode;
use NetFusion\Core\GraphQL\Parser\FragmentSpreadNode;
use NetFusion\Core\GraphQL\Parser\InlineFragmentNode;
use NetFusion\Core\GraphQL\Parser\IntValueNode;
use NetFusion\Core\GraphQL\Parser\ListValueNode;
use NetFusion\Core\GraphQL\Parser\Node;
use NetFusion\Core\GraphQL\Parser\NullValueNode;
use NetFusion\Core\GraphQL\Parser\ObjectValueNode;
use NetFusion\Core\GraphQL\Parser\OperationDefinitionNode;
use NetFusion\Core\GraphQL\Parser\SelectionSetNode;
use NetFusion\Core\GraphQL\Parser\StringValueNode;
use NetFusion\Core\GraphQL\Parser\VariableValueNode;
use NetFusion\Core\GraphQL\Types\EnumType;
use NetFusion\Core\GraphQL\Types\InterfaceType;
use NetFusion\Core\GraphQL\Types\ListType;
use NetFusion\Core\GraphQL\Types\NonNullType;
use NetFusion\Core\GraphQL\Types\ObjectType;
use NetFusion\Core\GraphQL\Types\ScalarType;
use NetFusion\Core\GraphQL\Types\Schema;
use NetFusion\Core\GraphQL\Types\UnionType;
use RuntimeException;
use Throwable;

class Executor
{
    /** @var array<string, FragmentDefinitionNode> */
    protected array $fragments = [];

    /**
     * Execute a GraphQL query document against a Schema.
     *
     * @param Schema               $schema
     * @param DocumentNode         $document
     * @param mixed                $rootValue
     * @param mixed                $contextValue
     * @param array<string, mixed> $variables
     * @param string|null          $operationName
     * @return ExecutionResult
     */
    public static function execute(
        Schema $schema,
        DocumentNode $document,
        mixed $rootValue = null,
        mixed $contextValue = null,
        array $variables = [],
        ?string $operationName = null
    ): ExecutionResult {
        $executor = new self();
        return $executor->run($schema, $document, $rootValue, $contextValue, $variables, $operationName);
    }

    public function run(
        Schema $schema,
        DocumentNode $document,
        mixed $rootValue = null,
        mixed $contextValue = null,
        array $variables = [],
        ?string $operationName = null
    ): ExecutionResult {
        $this->fragments = [];
        $operations = [];

        foreach ($document->definitions as $def) {
            if ($def instanceof OperationDefinitionNode) {
                $operations[] = $def;
            } elseif ($def instanceof FragmentDefinitionNode) {
                $this->fragments[$def->name] = $def;
            }
        }

        if (empty($operations)) {
            return new ExecutionResult(null, [['message' => 'Must provide an operation.']]);
        }

        $operation = null;
        if ($operationName === null) {
            if (count($operations) > 1) {
                return new ExecutionResult(null, [['message' => 'Must provide operation name if query contains multiple operations.']]);
            }
            $operation = $operations[0];
        } else {
            foreach ($operations as $op) {
                if ($op->name === $operationName) {
                    $operation = $op;
                    break;
                }
            }
            if ($operation === null) {
                return new ExecutionResult(null, [['message' => "Unknown operation named '{$operationName}'."]]);
            }
        }

        $coercedVariables = $this->coerceVariableValues($schema, $operation, $variables);
        $context = new ExecutionContext($schema, $coercedVariables, $rootValue, $contextValue);

        $rootType = match ($operation->operation) {
            'query'        => $schema->getQueryType(),
            'mutation'     => $schema->getMutationType() ?? throw new RuntimeException('Schema is not configured for mutations.'),
            'subscription' => $schema->getSubscriptionType() ?? throw new RuntimeException('Schema is not configured for subscriptions.'),
            default        => throw new RuntimeException("Unknown operation type [{$operation->operation}]."),
        };

        if ($operation->selectionSet === null) {
            return new ExecutionResult([], $context->getErrors());
        }

        $data = $this->executeSelectionSet($operation->selectionSet, $rootType, $rootValue, $context, []);

        return new ExecutionResult($data, $context->getErrors());
    }

    /**
     * Coerce provided variables against operation variable definitions.
     */
    protected function coerceVariableValues(Schema $schema, OperationDefinitionNode $operation, array $inputVariables): array
    {
        $coerced = [];

        foreach ($operation->variableDefinitions as $varDef) {
            $varName = $varDef->variableName;
            $value = $inputVariables[$varName] ?? null;

            if ($value === null && $varDef->defaultValue !== null) {
                $value = $this->evaluateValueNode($varDef->defaultValue, []);
            }

            if ($value !== null) {
                $coerced[$varName] = $value;
            }
        }

        return array_merge($inputVariables, $coerced);
    }

    /**
     * Execute selection set fields against target object type.
     */
    protected function executeSelectionSet(
        SelectionSetNode $selectionSet,
        ObjectType $objectType,
        mixed $source,
        ExecutionContext $context,
        array $path
    ): array {
        $result = [];
        $fields = $this->collectFields($selectionSet, $objectType, $context);

        foreach ($fields as $responseKey => $fieldNodes) {
            $fieldNode = $fieldNodes[0];
            $fieldName = $fieldNode->name;
            $currentPath = array_merge($path, [$responseKey]);

            $context->setFieldName($fieldName);
            $context->setPath($currentPath);

            // Introspection meta fields
            if ($fieldName === '__typename') {
                $result[$responseKey] = $objectType->getName();
                continue;
            }

            if ($fieldName === '__schema' && $objectType->getName() === 'Query') {
                $result[$responseKey] = $this->resolveSchemaIntrospection($context->schema);
                continue;
            }

            if ($fieldName === '__type' && $objectType->getName() === 'Query') {
                $args = $this->resolveFieldArguments($fieldNode, null, $context);
                $name = (string) ($args['name'] ?? '');
                $type = $context->schema->getType($name);
                $result[$responseKey] = $type !== null ? $this->resolveTypeIntrospection($type) : null;
                continue;
            }

            $fieldDef = $objectType->getField($fieldName);
            if ($fieldDef === null) {
                $context->addError("Cannot query field '{$fieldName}' on type '{$objectType->getName()}'.", $currentPath);
                $result[$responseKey] = null;
                continue;
            }

            $resolvedArgs = $this->resolveFieldArguments($fieldNode, $fieldDef, $context);

            try {
                $resolvedValue = $fieldDef->resolve($source, $resolvedArgs, $context->getContextValue(), $context);
                $completedValue = $this->completeValue($fieldDef->getType(), $fieldNodes, $resolvedValue, $context, $currentPath);
                $result[$responseKey] = $completedValue;
            } catch (Throwable $e) {
                $context->addError($e->getMessage(), $currentPath, $fieldNode->line, $fieldNode->column);
                $result[$responseKey] = null;
            }
        }

        return $result;
    }

    /**
     * Complete and format a resolved field value based on its type.
     */
    protected function completeValue(
        TypeInterface $type,
        array $fieldNodes,
        mixed $result,
        ExecutionContext $context,
        array $path
    ): mixed {
        // NonNull
        if ($type instanceof NonNullType) {
            $innerType = $type->getWrappedType();
            $completed = $this->completeValue($innerType, $fieldNodes, $result, $context, $path);
            if ($completed === null) {
                $context->addError("Cannot return null for non-nullable field at path " . implode('.', $path), $path);
                return null;
            }
            return $completed;
        }

        if ($result === null) {
            return null;
        }

        // List
        if ($type instanceof ListType) {
            if (!is_iterable($result)) {
                $context->addError("Expected iterable value for List type.", $path);
                return null;
            }

            $itemType = $type->getItemType();
            $completedList = [];
            $index = 0;

            foreach ($result as $item) {
                $itemPath = array_merge($path, [$index]);
                $completedList[] = $this->completeValue($itemType, $fieldNodes, $item, $context, $itemPath);
                $index++;
            }

            return $completedList;
        }

        // Scalar
        if ($type instanceof ScalarType) {
            return $type->serialize($result);
        }

        // Enum
        if ($type instanceof EnumType) {
            return $type->getNameForValue($result) ?? (string) $result;
        }

        // Object
        if ($type instanceof ObjectType) {
            $subSelectionSet = $fieldNodes[0]->selectionSet;
            if ($subSelectionSet === null) {
                return null;
            }
            return $this->executeSelectionSet($subSelectionSet, $type, $result, $context, $path);
        }

        // Interface or Union
        if ($type instanceof InterfaceType || $type instanceof UnionType) {
            $concreteType = $type->resolveType($result, $context->getContextValue(), $context);
            if (is_string($concreteType)) {
                $concreteType = $context->schema->getType($concreteType);
            }
            if (!($concreteType instanceof ObjectType)) {
                $context->addError("Could not resolve concrete type for {$type->getName()}.", $path);
                return null;
            }

            $subSelectionSet = $fieldNodes[0]->selectionSet;
            if ($subSelectionSet === null) {
                return null;
            }
            return $this->executeSelectionSet($subSelectionSet, $concreteType, $result, $context, $path);
        }

        return $result;
    }

    /**
     * Resolve argument values from field AST node and variables.
     */
    protected function resolveFieldArguments(FieldNode $fieldNode, ?\NetFusion\Core\GraphQL\Types\Field $fieldDef, ExecutionContext $context): array
    {
        $args = [];

        // Apply default values if defined
        if ($fieldDef !== null) {
            foreach ($fieldDef->getArguments() as $argDef) {
                if ($argDef->hasDefaultValue()) {
                    $args[$argDef->getName()] = $argDef->getDefaultValue();
                }
            }
        }

        foreach ($fieldNode->arguments as $argNode) {
            $name = $argNode->name;
            $value = $this->evaluateValueNode($argNode->value, $context->getVariables());
            $args[$name] = $value;
        }

        return $args;
    }

    /**
     * Evaluate AST ValueNode into raw PHP value.
     */
    protected function evaluateValueNode(Node $node, array $variables): mixed
    {
        if ($node instanceof VariableValueNode) {
            return $variables[$node->name] ?? null;
        }
        if ($node instanceof IntValueNode) {
            return $node->value;
        }
        if ($node instanceof FloatValueNode) {
            return $node->value;
        }
        if ($node instanceof StringValueNode) {
            return $node->value;
        }
        if ($node instanceof BooleanValueNode) {
            return $node->value;
        }
        if ($node instanceof NullValueNode) {
            return null;
        }
        if ($node instanceof EnumValueNode) {
            return $node->value;
        }
        if ($node instanceof ListValueNode) {
            $arr = [];
            foreach ($node->values as $valNode) {
                $arr[] = $this->evaluateValueNode($valNode, $variables);
            }
            return $arr;
        }
        if ($node instanceof ObjectValueNode) {
            $obj = [];
            foreach ($node->fields as $fieldNode) {
                $obj[$fieldNode->name] = $this->evaluateValueNode($fieldNode->value, $variables);
            }
            return $obj;
        }

        return null;
    }

    /**
     * Collect all matching fields from selection set (handling fragments & directives).
     *
     * @return array<string, array<FieldNode>>
     */
    protected function collectFields(SelectionSetNode $selectionSet, ObjectType $objectType, ExecutionContext $context): array
    {
        $fields = [];

        foreach ($selectionSet->selections as $selection) {
            if (!$this->shouldIncludeNode($selection, $context)) {
                continue;
            }

            if ($selection instanceof FieldNode) {
                $name = $selection->getResponseKey();
                $fields[$name][] = $selection;
            } elseif ($selection instanceof InlineFragmentNode) {
                if ($selection->typeCondition === null || $selection->typeCondition === $objectType->getName()) {
                    if ($selection->selectionSet !== null) {
                        $fragmentFields = $this->collectFields($selection->selectionSet, $objectType, $context);
                        foreach ($fragmentFields as $key => $nodes) {
                            foreach ($nodes as $node) {
                                $fields[$key][] = $node;
                            }
                        }
                    }
                }
            } elseif ($selection instanceof FragmentSpreadNode) {
                $fragmentDef = $this->fragments[$selection->name] ?? null;
                if ($fragmentDef !== null && $fragmentDef->selectionSet !== null) {
                    if ($fragmentDef->typeCondition === $objectType->getName()) {
                        $fragmentFields = $this->collectFields($fragmentDef->selectionSet, $objectType, $context);
                        foreach ($fragmentFields as $key => $nodes) {
                            foreach ($nodes as $node) {
                                $fields[$key][] = $node;
                            }
                        }
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Check @include and @skip directives on selection node.
     */
    protected function shouldIncludeNode(Node $node, ExecutionContext $context): bool
    {
        $directives = $node->directives ?? [];
        foreach ($directives as $directiveNode) {
            $directiveDef = $context->schema->getDirective($directiveNode->name);
            if ($directiveDef !== null) {
                $args = [];
                foreach ($directiveNode->arguments as $argNode) {
                    $args[$argNode->name] = $this->evaluateValueNode($argNode->value, $context->getVariables());
                }
                if (!$directiveDef->shouldInclude($args, $context->getContextValue())) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Minimal Schema Introspection payload for GraphiQL and client discovery.
     */
    protected function resolveSchemaIntrospection(Schema $schema): array
    {
        $types = [];
        foreach ($schema->getTypeMap() as $type) {
            $types[] = $this->resolveTypeIntrospection($type);
        }

        return [
            'queryType'        => ['name' => $schema->getQueryType()->getName()],
            'mutationType'     => $schema->getMutationType() ? ['name' => $schema->getMutationType()->getName()] : null,
            'subscriptionType' => $schema->getSubscriptionType() ? ['name' => $schema->getSubscriptionType()->getName()] : null,
            'types'            => $types,
        ];
    }

    protected function resolveTypeIntrospection(TypeInterface $type): array
    {
        $kind = match (true) {
            $type instanceof ScalarType      => 'SCALAR',
            $type instanceof ObjectType      => 'OBJECT',
            $type instanceof InterfaceType   => 'INTERFACE',
            $type instanceof UnionType       => 'UNION',
            $type instanceof EnumType        => 'ENUM',
            $type instanceof InputObjectType => 'INPUT_OBJECT',
            $type instanceof ListType        => 'LIST',
            $type instanceof NonNullType     => 'NON_NULL',
            default                          => 'OBJECT',
        };

        $fields = null;
        if ($type instanceof ObjectType || $type instanceof InterfaceType) {
            $fields = [];
            foreach ($type->getFields() as $field) {
                $fields[] = [
                    'name'        => $field->getName(),
                    'description' => $field->getDescription(),
                    'type'        => ['name' => $field->getType()->getName(), 'kind' => 'SCALAR'],
                ];
            }
        }

        return [
            'kind'        => $kind,
            'name'        => $type->getName(),
            'description' => $type->getDescription(),
            'fields'      => $fields,
        ];
    }
}
