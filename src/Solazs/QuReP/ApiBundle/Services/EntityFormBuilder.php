<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.01.07.
 * Time: 23:41
 */

namespace Solazs\QuReP\ApiBundle\Services;

use Solazs\QuReP\ApiBundle\Resources\Consts;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;
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
     * @param $entityClass
     * @param $entity
     * @return \Symfony\Component\Form\Form
     */
    public function getForm($entityClass, $entity) : Form
    {
        $properties = $this->entityParser->getProps($entityClass);

        if (count($properties) <= 1) {
            throw new HttpException(500, "There are no properties annotated with Type in ".$entityClass);
        }

        $formBuilder = $this->formFactory->createBuilder(
          'Symfony\Component\Form\Extension\Core\Type\FormType',
          $entity,
          [
            'data_class'      => $entityClass,
            'csrf_protection' => false,
          ]
        );
        foreach ($properties as $property) {
            switch ($property['propType']) {
                case (Consts::formProp):
                    $formBuilder->add(
                      $property['label'],
                      $property['type'],
                      $property['options'] === null ? [] : $property['options']
                    );
                    break;
                case (Consts::singleProp):
                    $formBuilder->add(
                      $property['label'],
                      EntityType::class,
                      [
                        'class' => $property['class'],
                      ]
                    );
                    break;
                case (Consts::pluralProp):
                    $formBuilder->add(
                      $property['label'],
                      EntityType::class,
                      [
                        'multiple' => true,
                        'class'    => $property['class'],
                      ]
                    );
                    break;
            }
        }

        return $formBuilder->getForm();

    }

}
