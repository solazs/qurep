<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.01.07.
 * Time: 23:41
 */

namespace Solazs\QuReP\ApiBundle\Services;

use Doctrine\Common\Cache\Cache;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EntityFormBuilder
{
    protected $formFactory;
    protected $cache;
    protected $entityParser;

    public function __construct(FormFactory $formFactory, Cache $cache, EntityParser $entityParser)
    {
        $this->formFactory = $formFactory;
        $this->cache = $cache;
        $this->entityParser = $entityParser;
    }

    public function getForm($entityClass, $entity)
    {
        if ($this->cache->contains($entityClass)) {
            $properties = $this->cache->fetch($entityClass);
        } else {
            $properties = $this->entityParser->getProps($entityClass);
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
                        $property['label'],
                        EntityType::class,
                        [
                            'class' => $property['class'],
                            'choice_label' => 'id'
                        ]
                    );
                    break;
                case 'plural':
                    $formBuilder->add(
                        $property['label'],
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

}
