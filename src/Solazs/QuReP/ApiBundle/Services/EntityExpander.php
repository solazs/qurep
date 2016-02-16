<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.02.11.
 * Time: 1:00
 */

namespace Solazs\QuReP\ApiBundle\Services;


class EntityExpander
{
    protected $entityParser;

    function __construct(EntityParser $entityParser)
    {
        $this->entityParser = $entityParser;
    }

    public function expandEntity($entityClass, $entity)
    {
        $properties = $this->entityParser->getProps($entityClass);
        $preppedEntity = [];

        //todo

        return $entity;
    }
}