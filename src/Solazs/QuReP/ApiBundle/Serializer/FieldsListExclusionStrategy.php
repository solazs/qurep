<?php

namespace Solazs\QuReP\ApiBundle\Serializer;


use JMS\Serializer\Context;
use JMS\Serializer\Exclusion\ExclusionStrategyInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use Solazs\QuReP\ApiBundle\Services\DataHandler;

/**
 * Class FieldsListExclusionStrategy
 *
 * Exclusion strategy for whitelisting fields during serialization.
 * Only the shouldSkipProperty function is implemented.
 *
 * @package Solazs\QuReP\ApiBundle\Serializer
 */
class FieldsListExclusionStrategy implements ExclusionStrategyInterface
{
    private $dataHandler = [];
    private $expands = [];

    public function __construct(DataHandler $dataHandler, array $expands)
    {
        $this->dataHandler = $dataHandler;
        $this->expands = $expands;
    }

    /**
     * Whether the class should be skipped.
     * Returns false as we are only concerned about properties here.
     *
     * @param ClassMetadata           $metadata
     *
     * @param \JMS\Serializer\Context $context
     * @return bool
     */
    public function shouldSkipClass(ClassMetadata $metadata, Context $context)
    {
        return false;
    }

    /**
     * Whether the property should be skipped.
     * Queries default fields from the dataHandler and the expands.
     *
     * @param PropertyMetadata        $property
     *
     * @param \JMS\Serializer\Context $context
     * @return bool
     */
    public function shouldSkipProperty(PropertyMetadata $property, Context $context)
    {
        $depth = $context->getDepth();
        $fields = $this->dataHandler->getFields($property->class);
        if (empty($fields)) {
            return false;
        }

        $name = $property->serializedName ?: $property->name;

        return (!(in_array($name, $fields)) && !($this->findProp($property->class, $name, $depth)));
    }

    private function findProp(string $class, string $name, int $depth) : bool
    {
        $cnt = 1;
        foreach ($this->expands as $expand) {
            if ($this->walkExpand($expand, $class, $name, $depth, $cnt)) {
                return true;
            }
        }

        return false;
    }

    private function walkExpand(array $expand, string $class, string $name, int $depth, int $cnt) : bool
    {
        if ($depth == $cnt && $expand['name'] == $name && $expand['class'] == $class) {
            return true;
        }

        if ($expand['children'] != null && ($depth != $cnt)) {
            return $this->walkExpand($expand['children'], $class, $name, $depth, $cnt + 1);
        }

        return false;
    }
}