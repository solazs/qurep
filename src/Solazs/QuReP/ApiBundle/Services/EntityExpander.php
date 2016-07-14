<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.02.19.
 * Time: 1:11
 */

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\ORM\EntityManagerInterface;
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
            $entity = $this->walkArray($expand, $entityClass, $entity, $dataHandler);
        }

        return $entity;
    }

    private function walkArray(array $expand, string $entityClass, $entity, DataHandler $dataHandler)
    {
        $entity = $this->doFill($expand, $entityClass, $entity, $dataHandler);
        if (array_key_exists($expand['name'], $entity) && $expand['children'] != null) {
            if (!array_key_exists($expand['name'], $entity)) {
                $entity[$expand['name']] = [];
            }
            $entity[$expand['name']] = $this->walkArray($expand['children'], $entityClass, $entity[$expand['name']], $dataHandler);
        }

        return $entity;
    }

    private function doFill(array $expand, string $entityClass, $entity, DataHandler $dataHandler)
    {
        if (array_key_exists($expand['name'],$entity)){
            return $entity;
        }
        $getter = "get".strtoupper(substr($expand['name'], 0, 1)).substr($expand['name'], 1);
        if (!array_key_exists('id', $entity)) {
            foreach ($entity as &$entityItem) {
                $subData = $this->em->getRepository($entityClass)->find($entityItem['id'])->$getter();
                if ($expand['propType'] == Consts::pluralProp) {
                    $entityItem[$expand['name']] = [];
                    if (count($subData) > 0) {
                        foreach ($subData as $item) {
                            $entityItem[$expand['name']][] = $dataHandler->get($expand['class'], $item->getId());
                        }
                    }
                } else {
                    if ($subData !== null) {
                        $entityItem[$expand['name']] = $dataHandler->get($expand['class'], $subData->getId());
                    }
                }
            }
        } else {
            $subData = $this->em->getRepository($entityClass)->find($entity['id'])->$getter();
            if ($expand['propType'] == Consts::pluralProp) {
                $entity[$expand['name']] = [];
                if (count($subData) > 0) {
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