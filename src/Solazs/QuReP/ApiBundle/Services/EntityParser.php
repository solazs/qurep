<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.02.06.
 * Time: 17:05
 */

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\Cache;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Solazs\QuReP\ApiBundle\Annotations\Entity\Type;

class EntityParser
{
    protected $cache;
    protected $reader;
    protected $entities;

    function __construct(Cache $cache)
    {
        $this->cache = $cache;
        $this->reader = new AnnotationReader();
    }

    public function setConfig($entities)
    {
        $this->entities = $entities;
    }

    public function getProps($entityClass) : array
    {
        $properties = [];
        $refClass = new \ReflectionClass($entityClass);

        $entityProperties = $refClass->getProperties();
        foreach ($entityProperties as $entityProperty) {
            $field = [];
            foreach ($this->reader->getPropertyAnnotations($entityProperty) as $annotation) {
                if ($annotation instanceof Type) {
                    $field["label"] =
                        $annotation->getLabel() === null ? $entityProperty->getName() : $annotation->getLabel();
                    $field["options"] = $annotation->getOptions();
                    $field["type"] = $annotation->getType();
                    $field["propType"] = "prop";
                }
            }
            foreach ($this->reader->getPropertyAnnotations($entityProperty) as $annotation) {
                if ($annotation instanceof OneToOne) {
                    if (!array_key_exists('label', $field)) {
                        $field['label'] = $entityProperty->getName();
                    }
                    $field["propType"] = "single";
                    $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                } elseif ($annotation instanceof OneToMany) {
                    if (!array_key_exists('label', $field)) {
                        $field['label'] = $entityProperty->getName();
                    }
                    $field["propType"] = "plural";
                    $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                } elseif ($annotation instanceof ManyToOne) {
                    if (!array_key_exists('label', $field)) {
                        $field['label'] = $entityProperty->getName();
                    }
                    $field["propType"] = "single";
                    $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                }
            }

            if (!in_array($field, $properties) && $field != []) {
                array_push($properties, $field);
            }
        }
        return $properties;
    }

    protected
    function getEntityClass(
        $entityClass,
        $className
    ) {
        if (class_exists($className)) {
            return $className;
        } else {
            $fullClassName = substr($entityClass, 0, strrpos($entityClass, '\\')) . '\\' . $className;
            if (class_exists($fullClassName)) {
                return substr($entityClass, 0, strrpos($entityClass, '\\')) . '\\' . $className;
            } else {
                throw new AnnotationException("Cannot find related entity '" . $className . "' in " . $entityClass);
            }
        }
    }

    public function getEntityName($entityClass)
    {
        foreach ($this->entities as $entity) {
            if ($entity['class'] == $entityClass) {
                return $entity['entity_name'];
            }
        }
        return null;
    }

}