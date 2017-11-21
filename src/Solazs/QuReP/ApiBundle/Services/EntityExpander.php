<?php

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\ODM\MongoDB\PersistentCollection as MongoDBPersistentCollection;
use Doctrine\ODM\PHPCR\PersistentCollection as PHPCRPersistentCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Persistence\Proxy;
use Doctrine\ORM\PersistentCollection;
use Solazs\QuReP\ApiBundle\Resources\PropType;

/**
 * Class EntityExpander
 *
 * This class is responsible for the expand functionality.
 * It walks through the expand statements and fills the entity (entities) as necessary with additional data.
 *
 * @package Solazs\QuReP\ApiBundle\Services
 */
class EntityExpander
{
    /** @var \Doctrine\ORM\EntityManagerInterface $em */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

    }

    /**
     * Recursively walk through expand statements and fill necessary properties of the supplied entity.
     *
     * @param array                                        $expands
     * @param string                                       $entityClass
     * @param                                              $entity
     * @param bool                                         $isCollection
     * @param \Solazs\QuReP\ApiBundle\Services\DataHandler $dataHandler
     * @return mixed
     */
    public function expandEntity(array $expands, string $entityClass, $entity, bool $isCollection, DataHandler $dataHandler)
    {
        if ($isCollection) {
            foreach ($entity as &$item) {
                $item = $this->fillEntity($expands, $entityClass, $item, $dataHandler);
            }
        } else {
            $item = $this->fillEntity($expands, $entityClass, $entity, $dataHandler);
            $entity = $item;
        }

        return $entity;
    }

    /*
     * Functions required to do the recursive walking of the expands *
     */

    private function fillEntity($expands, $entityClass, $entity, DataHandler $dataHandler)
    {
        foreach ($expands as $expand) {
            $this->walkArray($expand, $entityClass, $entity, $dataHandler);
        }

        return $entity;
    }

    private function walkArray(array $expand, string $entityClass, $entity, DataHandler $dataHandler)
    {
        $getter = "get".strtoupper(substr($expand['name'], 0, 1)).substr($expand['name'], 1);
        $entity = $this->doFill($entity);
        if ($expand['children'] !== null) {
            if ($expand['children']['propType'] === PropType::PLURAL_PROP) {
                foreach ($entity as $item) {
                    $this->walkArray(
                      $expand['children'],
                      $entityClass,
                      $item,
                      $dataHandler
                    );
                }
            } else {
                $this->walkArray(
                  $expand['children'],
                  $entityClass,
                  $entity->$getter(),
                  $dataHandler
                );
            }
        } else {
            $entity = $this->doFill($entity, $getter);
        }

        return $entity;
    }

    private function doFill($entity, $getter = null)
    {
        if ($getter !== null) {
            $subject = $entity->$getter();
        } else {
            $subject = $entity;
        }
        if ($subject instanceof Proxy) {
            /** @var Proxy $entity */
            $subject->__load();
        }
        if ($subject instanceof PersistentCollection
          || $subject instanceof MongoDBPersistentCollection
          || $subject instanceof PHPCRPersistentCollection
        ) {
            $subject->initialize();
        }

        return $entity;
    }

}