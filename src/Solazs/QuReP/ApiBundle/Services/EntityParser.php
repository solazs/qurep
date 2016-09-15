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

    function __construct(Cache $cache)
    {
        $this->cache = $cache;
        $this->reader = new AnnotationReader();
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
    public function getProps(string $entityClass) :array
    {
        if ($this->cache->contains($entityClass)) {
            $properties = $this->cache->fetch($entityClass);
        } else {
            $properties = $this->parseProps($entityClass);
            $this->cache->save($entityClass, $properties, 3600);
        }

        return $properties;
    }

    /**
     * Actually parses the entities listed in the QuReP configuration using reflection.
     * Property types are listed in @link [\Solazs\QuReP\ApiBundle\Resources\Consts] [the Consts class]
     *
     * The method returns an array of the detected fields with the following properties:
     *  - label      label to use on api
     *  - propType   proptype
     *  - name       name of property
     * The following properties are also listed if the prop is a formProp
     *  - type       form field type
     *  - options    form field type options
     *
     * @param $entityClass
     * @return array
     */
    protected function parseProps($entityClass) : array
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
                    $field["propType"] = Consts::formProp;
                    $field['name'] = $entityProperty->getName();
                }
            }
            foreach ($this->reader->getPropertyAnnotations($entityProperty) as $annotation) {
                if ($annotation instanceof OneToOne) {
                    if (!array_key_exists('label', $field)) {
                        $field['label'] = $entityProperty->getName();
                    }
                    $field['name'] = $entityProperty->getName();
                    $field["propType"] = Consts::singleProp;
                    $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                } elseif ($annotation instanceof OneToMany) {
                    if (!array_key_exists('label', $field)) {
                        $field['label'] = $entityProperty->getName();
                    }
                    $field['name'] = $entityProperty->getName();
                    $field["propType"] = Consts::pluralProp;
                    $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                } elseif ($annotation instanceof ManyToOne) {
                    if (!array_key_exists('label', $field)) {
                        $field['label'] = $entityProperty->getName();
                    }
                    $field['name'] = $entityProperty->getName();
                    $field["propType"] = Consts::singleProp;
                    $field['class'] = $this->getEntityClass($entityClass, $annotation->targetEntity);
                } else {
                    if (count($field) == 0) {
                        $field["propType"] = Consts::prop;
                        $field['label'] = $entityProperty->getName();
                        $field['name'] = $entityProperty->getName();
                    }
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