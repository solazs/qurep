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
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EntityFormBuilder
{
    protected $formFactory;
    protected $entityParser;

    public function __construct(FormFactory $formFactory, EntityParser $entityParser)
    {
        $this->formFactory = $formFactory;
        $this->entityParser = $entityParser;
    }

    public function getForm($entityClass, $entity)
    {
        $properties = $this->entityParser->getProps($entityClass);

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
                            'class' => $property['class']
                        ]
                    );
                    break;
                case (Consts::pluralProp):
                    $formBuilder->add(
                        $property['label'],
                        EntityType::class,
                        [
                            'multiple' => true,
                            'class' => $property['class']
                        ]
                    );
                    break;
            }
        }

        return $formBuilder->getForm();

    }

}
