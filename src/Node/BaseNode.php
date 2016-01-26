<?php
declare(strict_types=1);

namespace Linio\Component\Input\Node;

use Linio\Component\Input\Constraint\ConstraintInterface;
use Linio\Component\Input\Exception\InvalidConstraintException;
use Linio\Component\Input\Exception\RequiredFieldException;
use Linio\Component\Input\Instantiator\InstantiatorInterface;
use Linio\Component\Input\Transformer\TransformerInterface;
use Linio\Component\Input\TypeHandler;

class BaseNode
{
    /**
     * @var ConstraintInterface[]
     */
    protected $constraints = [];

    /**
     * @var TransformerInterface
     */
    protected $transformer;

    /**
     * @var InstantiatorInterface
     */
    protected $instantiator;

    /**
     * @var string
     */
    protected $type = 'array';

    /**
     * @var bool
     */
    protected $required = true;

    /**
     * @var mixed
     */
    protected $default;

    /**
     * @var BaseNode[]
     */
    protected $children = [];

    /**
     * @var TypeHandler
     */
    protected $typeHandler;

    public function setConstraints(array $constraints)
    {
        $this->constraints = $constraints;
    }

    public function addConstraint(ConstraintInterface $constraint)
    {
        $this->constraints[] = $constraint;
    }

    public function setTransformer(TransformerInterface $transformer)
    {
        $this->transformer = $transformer;
    }

    public function setInstantiator(InstantiatorInterface $instantiator)
    {
        $this->instantiator = $instantiator;
    }

    public function setTypeHandler(TypeHandler $typeHandler)
    {
        $this->typeHandler = $typeHandler;
    }

    public function setType(string $type)
    {
        $this->type = $type;
    }

    public function setRequired(bool $required)
    {
        $this->required = $required;
    }

    public function setDefault($default)
    {
        $this->default = $default;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function hasDefault(): bool
    {
        return (bool) $this->default;
    }

    public function add(string $key, string $type, array $options = []): BaseNode
    {
        $child = $this->typeHandler->getType($type);
        $child->setTypeHandler($this->typeHandler);

        if (isset($options['required'])) {
            $child->setRequired($options['required']);
        }

        if (isset($options['default'])) {
            $child->setDefault($options['default']);
        }

        if (isset($options['instantiator'])) {
            $child->setInstantiator($options['instantiator']);
        }

        if (isset($options['transformer'])) {
            $child->setTransformer($options['transformer']);
        }

        if (isset($options['constraints'])) {
            $child->setConstraints($options['constraints']);
        }

        $this->children[$key] = $child;

        return $child;
    }

    public function remove(string $key)
    {
        unset($this->children[$key]);
    }

    /**
     * @return BaseNode[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    public function isRequired(): bool
    {
        if ($this->hasDefault()) {
            return false;
        }

        return $this->required;
    }

    public function getValue(string $field, $value)
    {
        $this->checkConstraints($field, $value);

        if ($this->transformer) {
            return $this->transformer->transform($value);
        }

        return $value;
    }

    public function walk($input)
    {
        $result = [];

        if (!$this->hasChildren()) {
            return $input;
        }

        foreach ($this->getChildren() as $field => $config) {
            if (!array_key_exists($field, $input)) {
                if ($config->isRequired()) {
                    throw new RequiredFieldException($field);
                }

                if (!$config->hasDefault()) {
                    continue;
                }

                $input[$field] = $config->getDefault();
            }

            $result[$field] = $config->getValue($field, $config->walk($input[$field]));
        }

        return $result;
    }

    protected function checkConstraints(string $field, $value)
    {
        foreach ($this->constraints as $constraint) {
            if (!$constraint->validate($value)) {
                throw new InvalidConstraintException($constraint->getErrorMessage($field));
            }
        }
    }
}