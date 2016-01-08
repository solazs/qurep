<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.01.07.
 * Time: 23:41
 */

namespace QuReP\ApiBundle\Services;


use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ApcuCache;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use QuReP\ApiBundle\Annotations\Entity\Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EntityFormBuilder
{
    protected $reader;
    protected $formFactory;
    protected $cache;
    protected $entities;

    public function setConfig($entities)
    {
        $this->entities = $entities;
    }

    public function __construct(FormFactory $formFactory)
    {
        $this->reader = new AnnotationReader();
        $this->formFactory = $formFactory;
        $this->cache = new ApcuCache();
        $this->cache->setNamespace('qurep_form_props_');
    }

    public function getForm($entityClass, $entity)
    {
        if ($this->cache->contains($entityClass)) {
            $properties = $this->cache->fetch($entityClass);
        } else {
            $properties = $this->getProps($entityClass);
            $this->cache->save($entityClass, $properties, 3600);
        }

        if (count($properties) <= 1) {
            throw new HttpException(500, "There are no properties annotated with Type in " . $entityClass);
        }

        $formBuilder = $this->formFactory->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', $entity,
            [
                'data_class' => $entityClass,
                'csrf_protection' => false
            ]);
        foreach ($properties as $property) {
            switch ($property['propType']) {
                case 'prop':
                    $formBuilder->add(
                        $property['label'],
                        $property['type'],
                        $property['options'] === null ? [] : $property['options']
                    );
                    break;
                case 'single':
                    $formBuilder->add(
                        array_key_exists('label', $property) ? $property['label'] : $property['entityName'],
                        EntityType::class,
                        [
                            'class' => $property['class']
                        ]
                    );
                    break;
                case 'plural':
                    $formBuilder->add(
                        array_key_exists('label', $property) ? $property['label'] : $property['entityName'],
                        EntityType::class,
                        [
                            'multiple' => true,
                            'class' => $property['class'],
                            'choice_label' => 'id'
                        ]
                    );
                    break;
            }
        }

        return $formBuilder->getForm();

    }

    protected function getProps($entityClass) : array
    {
        $properties = [];
        $refClass = new \ReflectionClass($entityClass);

        $entityProperties = $refClass->getProperties();
        foreach ($entityProperties as $entityProperty) {
            $field = [];
            foreach ($this->reader->getPropertyAnnotations($entityProperty) as $annotation) {
                if ($annotation instanceof Type) {
                    $field["label"] =
                        $annotation->getLabel() === null ? $entityProperty->getName() : $annotation->getType();
                    $field["options"] = $annotation->getOptions();
                    $field["type"] = $annotation->getType();
                    $field["propType"] = "prop";
                } elseif ($annotation instanceof OneToOne) {
                    $class = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    $field["propType"] = "single";
                    $field['class'] = $class;
                    $field['entityName'] = $this->getEntityName($class);
                } elseif ($annotation instanceof OneToMany) {
                    $class = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    $field["propType"] = "plural";
                    $field['class'] = $class;
                    $field['entityName'] = $this->getEntityName($class);
                } elseif ($annotation instanceof ManyToOne) {
                    $class = $this->getEntityClass($entityClass, $annotation->targetEntity);
                    $field["propType"] = "single";
                    $field['class'] = $class;
                    $field['entityName'] = $this->getEntityName($class);
                }

                if (!in_array($field, $properties)) {
                    array_push($properties, $field);
                }
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

    protected
    function getEntityName(
        $entityClass
    ) {
        $entity = array_search($entityClass, $this->entities);
        return $entity['entity_name'];
    }

}
