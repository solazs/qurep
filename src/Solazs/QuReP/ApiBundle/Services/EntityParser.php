<?php

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\Cache;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Naming\CamelCaseNamingStrategy;
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
    protected $namingStrategy;

    function __construct(Cache $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->reader = new AnnotationReader();
        $this->props = null;
        $this->namingStrategy = new CamelCaseNamingStrategy();
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
                $this->logger->debug($this->loglbl.'Props data with id "'.$entityClass.'" found in cache.');
                $this->props = $this->cache->fetch($entityClass);
                if (!$this->props) {
                    $this->logger->critical(
                      $this->loglbl.'Cache fetch() resulted false, but cache contains() resulted true. '
                      .'This means something is not OK with the cache.'
                    );
                    $this->props = $this->parseProps($entityClass);
                }
            } else {
                $this->logger->debug($this->loglbl.'Props data with id "'.$entityClass.'" not found in cache.');
                $this->props = $this->parseProps($entityClass);
                $this->logger->debug($this->loglbl.'Props data with id "'.$entityClass.'" generated.');
                $this->cache->save($entityClass, $this->props, 3600);
                $this->logger->debug($this->loglbl.'Props data with id "'.$entityClass.'" saved to cache.');
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
        $this->logger->debug($this->loglbl.'Parsing props data of class "'.$entityClass.'"');
        $properties = [];
        $refClass = new \ReflectionClass($entityClass);

        $propertiesWithoutType = [];

        $entityProperties = $refClass->getProperties();
        foreach ($entityProperties as $entityProperty) {
            $field = [];
            $hadField = false;
            foreach ($this->reader->getPropertyAnnotations($entityProperty) as $annotation) {
                if ($annotation instanceof Field) {
                    $this->logger->debug(
                      $this->loglbl.'Found property '.$entityProperty->getName().' of class "'
                      .$entityClass.'" with Field annotation'
                    );
                    if ($annotation->getType() === null) {
                        $propertiesWithoutType[] = $entityProperty->getName();
                    }
                    $hadField = true;
                    if ($annotation->getLabel() === null) {
                        $propMetadata = new PropertyMetadata($entityClass, $entityProperty->getName());
                        $field['label'] = $this->namingStrategy->translateName($propMetadata);
                    } else {
                        $field['label'] = $annotation->getLabel();
                    }
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
                        $propertiesWithoutType = $this->examineType(
                          $field,
                          $entityProperty->getName(),
                          $entityClass,
                          $propertiesWithoutType
                        );
                        $field['propType'] = PropType::SINGLE_PROP;
                        $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    } elseif ($annotation instanceof OneToMany) {
                        $propertiesWithoutType = $this->examineType(
                          $field,
                          $entityProperty->getName(),
                          $entityClass,
                          $propertiesWithoutType
                        );
                        $field['propType'] = PropType::PLURAL_PROP;
                        $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    } elseif ($annotation instanceof ManyToOne) {
                        $propertiesWithoutType = $this->examineType(
                          $field,
                          $entityProperty->getName(),
                          $entityClass,
                          $propertiesWithoutType
                        );
                        $field['propType'] = PropType::SINGLE_PROP;
                        $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    } elseif ($annotation instanceof ManyToMany) {
                        $propertiesWithoutType = $this->examineType(
                          $field,
                          $entityProperty->getName(),
                          $entityClass,
                          $propertiesWithoutType
                        );
                        $field['propType'] = PropType::PLURAL_PROP;
                        $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    }
                }
            }

            if (!in_array($field, $properties) && $field != []) {
                array_push($properties, $field);
                $this->logger->debug(
                  $this->loglbl.'Property '.$entityProperty->getName().' of class "'.$entityClass
                  .'" has been parsed',
                  $field
                );
            }
        }


        if (count($propertiesWithoutType) > 0) {
            $this->logger->warning(
              $this->loglbl.'Property '.implode(', ', $propertiesWithoutType).' of entity '
              .$entityClass.' has no type in Field annotation. This means you will not be able to POST this property '
              .'to the API. Are you sure this is meant to be read-only?'
            );
        }


        $this->logger->debug(
          $this->loglbl.'Properties of class "'.$entityClass.'" has been parsed',
          $properties
        );

        return $properties;
    }

    protected function examineType(array $field, string $entityName, string $entityClass, array $propertiesWithoutType)
    {
        if ($field['type'] !== null) {
            $this->logger->warning(
              $this->loglbl.'Property '.$entityName.' of entity '
              .$entityClass.' has type in Field annotation, but relations will always be of type EntityType.'
            );
        } else {
            $propertiesWithoutType = array_diff($propertiesWithoutType, [$entityName]);
        }

        return $propertiesWithoutType;
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