<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.12.10.
 * Time: 20:51
 */

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DataHandler
{
    /* @var $em \Doctrine\ORM\EntityManager */
    protected $em;
    protected $entityFormBuilder;

    function __construct(EntityManager $entityManager, EntityFormBuilder $entityFormBuilder)
    {
        $this->em = $entityManager;
        $this->entityFormBuilder = $entityFormBuilder;
    }

    function get(string $entityClass, int $id = 0)
    {
        $data = $this->em->getRepository($entityClass)->find($id);
        if (!$data) {
            throw new NotFoundHttpException('Entity with id ' . $id . ' was not found in the database.');
        }
        return $data;
    }

    function getAll(string $entityClass)
    {
        return $this->em->getRepository($entityClass)->findAll();
    }

    function update(string $entityClass, $id = 0, array $postData = array())
    {
        if (count($postData) == 0) {
            throw new BadRequestHttpException("No data supplied");
        }
        $entity = $this->em->getRepository($entityClass)->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Entity with id ' . $id . ' was not found in the database.');
        }

        /** @var Form $form */
        $form = $this->entityFormBuilder->getForm($entityClass, $entity);
        $form->submit($postData);

        if ($form->isValid()) {
            $this->em->persist($entity);
            $this->em->flush();

            return $entity;
        } else {
            return $form;
        }

    }

    function bulkUpdate(string $entityClass, array $postData = array())
    {
        if (!is_array($postData)) {
            throw new BadRequestHttpException("Data is not an array");
        }
        if (count($postData) == 0) {
            throw new BadRequestHttpException("Data is an empty array");
        }
        foreach ($postData as $item) {
            if (array_key_exists('id', $item)) {
                $data = $item;
                unset($data['id']);
                return $this->update($entityClass, $item['id'], $data);
            } else {
                return $this->create($entityClass, $item);
            }
        }

        // Should not reach this
        return null;
    }

    function create(string $entityClass, array $postData = array())
    {
        if (count($postData) == 0) {
            throw new BadRequestHttpException("No data supplied");
        }
        $entity = new $entityClass;

        /** @var Form $form */
        $form = $this->entityFormBuilder->getForm($entityClass, $entity);
        $form->submit($postData);

        if ($form->isValid()) {
            $this->em->persist($entity);
            $this->em->flush();

            return $entity;
        } else {
            return $form;
        }

    }

    function delete(string $entityClass, int $id = 0)
    {
        $entity = $this->em->getRepository($entityClass)->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Entity with id ' . $id . ' was not found in the database.');
        }
        $this->em->remove($entity);
        $this->em->flush();
    }

    function deleteCollection(string $entityClass, array $postData = array())
    {
        $ids = [];
        foreach ($postData as $item) {
            array_push($ids, $item['id']);
        }
        $this->em->createQueryBuilder()
            ->delete($entityClass)
            ->where('id IN :ids')
            ->setParameter(':ids', $ids)
            ->getQuery()
            ->execute();
    }
}