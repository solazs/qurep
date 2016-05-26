<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.02.19.
 * Time: 1:11
 */

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\Proxy;
use Solazs\QuReP\ApiBundle\Resources\Consts;

class EntityExpander
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

    }

    public function expandEntity($expands, $entityClass, $entity, $isCollection, DataHandler $dataHandler)
    {
        if ($isCollection) {
            foreach ($entity as &$item) {
                $item = $this->fillEntity($expands, $entityClass, $item, $dataHandler);
            }
        } else {
            $entity = $this->fillEntity($expands, $entityClass, $entity, $dataHandler);
        }

        return $entity;
    }

    protected function fillEntity($expands, $entityClass, $entity, DataHandler $dataHandler)
    {
        foreach ($expands as $expand) {
            $getter = "get".strtoupper(substr($expand['name'], 0, 1)).substr($expand['name'], 1);
            $subData = $this->em->getRepository($entityClass)->find($entity['id'])->$getter();
            if ($expand['propType'] == Consts::pluralProp) {
                $entity[$expand['name']] = [];
                if (count($subData)>0) {
                    foreach ($subData as $item) {
                        $entity[$expand['name']][] = $dataHandler->get($expand['class'], $item->getId());
                    }
                }
            } else {
                if ($subData !== null) {
                    $entity[$expand['name']] = $dataHandler->get($expand['class'], $subData->getId());
                }
            }
        }

        return $entity;
    }

}