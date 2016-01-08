<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.01.07.
 * Time: 23:41
 */

namespace QuReP\ApiBundle\Services;


use Doctrine\Common\Annotations\AnnotationReader;
use QuReP\ApiBundle\Annotations\Entity\Type;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EntityFormBuilder implements IEntityFormBuilder
{
    protected $reader;
    protected $formFactory;

    public function __construct(FormFactory $formFactory)
    {
        $this->reader = new AnnotationReader();
        $this->formFactory = $formFactory;
    }

    public function getForm(string $entityClass): Form {
        $properties = $this->getProps($entityClass);

        if (count($properties)<=1){
            throw new HttpException(500, "There are no properties annotated with Type in " . $entityClass);
        }

        $formBuilder = $this->formFactory->createBuilder();
        foreach ($properties as $property) {
            $formBuilder->add(
                $property['label'],
                $property['type'],
                $property['options'] === null ? [] : $property['options']);
        }

        return $formBuilder->getForm();

    }

    private function getProps($entityClass) : array {
        $properties = [];
        $refClass = new \ReflectionClass($entityClass);

        $entityProperties = $refClass->getProperties();
        foreach ($entityProperties as $entityProperty) {
            foreach ($this->reader->getPropertyAnnotations($entityProperty) as $annotation) {
                if ($annotation instanceof Type){
                    $field = [
                        "label" => $annotation->getLabel() === null ? $entityProperty->getName() : $annotation->getType(),
                        "options" => $annotation->getOptions(),
                        "type" => $annotation->getType()];
                    if (!in_array($field, $properties)){
                        array_push($properties, $field);
                    }
                }
            }
        }
        return $properties;
    }



}