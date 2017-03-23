<?php

namespace Solazs\QuReP\ApiBundle\Services;

use Solazs\QuReP\ApiBundle\Annotations\Entity\Field;
use Solazs\QuReP\ApiBundle\Resources\PropType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class EntityFormBuilder
 *
 * The sole responsibility of this class is to create Symfony Forms for inserting/updating data.
 *
 * @package Solazs\QuReP\ApiBundle\Services
 */
class EntityFormBuilder
{
    /** @var \Symfony\Component\Form\FormFactory $formFactory */
    protected $formFactory;
    /** @var \Solazs\QuReP\ApiBundle\Services\EntityParser $entityParser */
    protected $entityParser;

    public function __construct(FormFactory $formFactory, EntityParser $entityParser)
    {
        $this->formFactory = $formFactory;
        $this->entityParser = $entityParser;
    }

    /**
     * Creates a form based on $entity.
     * To do this, $entityParser supplies the props of the class which are then mapped to form fields.
     *
     * @param string $entityClass
     * @param mixed  $entity
     * @return \Symfony\Component\Form\FormInterface
     */
    public function getForm(string $entityClass, $entity): FormInterface
    {
        $properties = $this->entityParser->getProps($entityClass);

        if (count($properties) <= 1) {
            throw new HttpException(500, 'There are no properties annotated with '.Field::class.' in '.$entityClass);
        }

        $formBuilder = $this->formFactory->createBuilder(
          FormType::class,
          $entity,
          [
            'data_class'      => $entityClass,
            'csrf_protection' => false,
          ]
        );
        foreach ($properties as $property) {
            switch ($property['propType']) {
                case (PropType::TYPED_PROP):
                    $opts = $property['options'] === null ? [] : $property['options'];
                    $opts['property_path'] = $property['name'];
                    $formBuilder->add(
                      $property['label'],
                      $property['type'],
                      $opts
                    );
                    break;
                case (PropType::SINGLE_PROP):
                    $opts = $property['options'] === null ? [] : $property['options'];
                    $opts['property_path'] = $property['name'];
                    $opts['class'] = $property['class'];
                    $formBuilder->add(
                      $property['label'],
                      EntityType::class,
                      $opts
                    );
                    break;
                case (PropType::PLURAL_PROP):
                    $opts = $property['options'] === null ? [] : $property['options'];
                    $opts['property_path'] = $property['name'];
                    $opts['class'] = $property['class'];
                    $opts['multiple'] = true;
                    $formBuilder->add(
                      $property['label'],
                      EntityType::class,
                      $opts
                    );
                    break;
            }
        }

        return $formBuilder->getForm();

    }

}
