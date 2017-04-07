<?php

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\Cache;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Psr\Log\LoggerInterface;
use Solazs\QuReP\ApiBundle\Annotations\Entity\Field;
use Solazs\QuReP\ApiBundle\Resources\PropType;
use Solazs\QuReP\ApiBundle\Resources\Consts;

/**
 * Class EntityParser
 *
 * Parses and stores entity metadata.
 *
 * @package Solazs\QuReP\ApiBundle\Services
 */
class EntityParser
{
    /** @var \Doctrine\Common\Cache\Cache $cache */
    protected $cache;
    /** @var \Doctrine\Common\Annotations\AnnotationReader $reader */
    protected $reader;
    protected $entities;
    protected $logger;
    protected $loglbl = Consts::qurepLogLabel.'EntityParser: ';
    protected $props;

    function __construct(Cache $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->reader = new AnnotationReader();
        $this->props = null;
    }

    public function setConfig($entities)
    {
        $this->entities = $entities;
    }

    /**
     * Fetches entity metadata from cache (or populates the cache if necessary)
     *
     * @param string $entityClass
     * @return array
     */
    public function getProps(string $entityClass): array
    {
        if ($this->props === null) {
            if ($this->cache->contains($entityClass)) {
                $this->props = $this->cache->fetch($entityClass);
                if (!$this->props) {
                    $this->logger->error(
                      $this->loglbl.'Cache fetch() resulted false, but cache contains() resulted true. '
                      .'This means something is not OK with the cache.'
                    );
                    $this->props = $this->parseProps($entityClass);
                }
            } else {
                $this->props = $this->parseProps($entityClass);
                $this->cache->save($entityClass, $this->props, 3600);
            }
        }

        return $this->props;
    }

    /**
     * Actually parses the entities listed in the QuReP configuration using reflection.
     * Property types are listed in @link [\Solazs\QuReP\ApiBundle\Resources\Consts] [the Consts class]
     *
     * The method returns an array of the detected fields with the following properties:
     *  - label      label to use on api
     *  - propType   propType
     *  - name       name of property
     * The following properties are also listed if the prop is a formProp
     *  - type       form field type
     *  - options    form field type options
     *
     * @param $entityClass
     * @return array
     */
    protected function parseProps($entityClass): array
    {
        $properties = [];
        $refClass = new \ReflectionClass($entityClass);

        $entityProperties = $refClass->getProperties();
        foreach ($entityProperties as $entityProperty) {
            $field = [];
            $hadField = false;
            foreach ($this->reader->getPropertyAnnotations($entityProperty) as $annotation) {
                if ($annotation instanceof Field) {
                    if ($annotation->getType() === null) {
                        $this->logger->warning(
                          $this->loglbl.'Property '.$entityProperty->getName().' of entity '
                          .$entityClass.' has no type in Field annotation. This means you will not be able to POST this property '
                          .'to the API. Are you sure this is meant to be read-only?'
                        );
                    }
                    $hadField = true;
                    $field['label'] =
                      $annotation->getLabel() === null ? $entityProperty->getName() : $annotation->getLabel();
                    $field['options'] = $annotation->getOptions();
                    $field['type'] = $annotation->getType();
                    $field['propType'] = $field['type'] === null ? PropType::PROP : PropType::TYPED_PROP;
                    $field['name'] = $entityProperty->getName();
                    $field['class'] = null;
                }
            }
            foreach ($this->reader->getPropertyAnnotations($entityProperty) as $annotation) {
                if ($hadField) {
                    if ($annotation instanceof OneToOne) {
                        $this->warnType($field, $entityProperty->getName(), $entityClass);
                        $field['propType'] = PropType::SINGLE_PROP;
                        $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    } elseif ($annotation instanceof OneToMany) {
                        $this->warnType($field, $entityProperty->getName(), $entityClass);
                        $field['propType'] = PropType::PLURAL_PROP;
                        $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    } elseif ($annotation instanceof ManyToOne) {
                        $this->warnType($field, $entityProperty->getName(), $entityClass);
                        $field['propType'] = PropType::SINGLE_PROP;
                        $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    } elseif ($annotation instanceof ManyToMany) {
                        $this->warnType($field, $entityProperty->getName(), $entityClass);
                        $field['propType'] = PropType::PLURAL_PROP;
                        $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    }
                }
            }

            if (!in_array($field, $properties) && $field != []) {
                array_push($properties, $field);
            }
        }

        return $properties;
    }

    protected function warnType(array $field, string $entityName, string $entityClass)
    {
        if ($field['type'] !== null) {
            $this->logger->warning(
              $this->loglbl.'Property '.$entityName.' of entity '
              .$entityClass.' has type in Field annotation, but relations will always be of type EntityType.'
            );
        }
    }

    protected function getEntityClass(string $entityClass, string $className): string
    {
        if (class_exists($className)) {
            return $className;
        } else {
            $fullClassName = substr($entityClass, 0, strrpos($entityClass, '\\')).'\\'.$className;
            if (class_exists($fullClassName)) {
                return substr($entityClass, 0, strrpos($entityClass, '\\')).'\\'.$className;
            } else {
                throw new AnnotationException("Cannot find related entity '".$className."' in ".$entityClass);
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