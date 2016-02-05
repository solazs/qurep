<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.12.10.
 * Time: 20:51
 */

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
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

    protected function buildFilterSubQuery(QueryBuilder $qb, array $filters, array &$parameters)
    {
        $conditions = [];
        foreach ($filters as $filterGrp) {
            $andConditions = [];
            foreach ($filterGrp as $filter) {
                $andConditions[] = $qb->expr()->$filter['operand']($filter["prop"], ":" . $filter['prop']);
                $parameters[":" . $filter['prop']] = $filter['value'];
            }
            $andConditions = call_user_func_array([$qb->expr(), "andx"], $andConditions);
            $conditions[] = $andConditions;
        }
        $conditions = call_user_func_array(array($qb->expr(), "orx"), $conditions);

        return $conditions;
    }

    function get(string $entityClass, int $id = 0)
    {
        $data = $this->em->getRepository($entityClass)->find($id);

        if (!$data) {
            throw new NotFoundHttpException('Entity with id ' . $id . ' was not found in the database.');
        }
        return $data;
    }

    function getAll(string $entityClass, array $filters = array())
    {
        $parameters = [];
        $qb = $this->em->createQueryBuilder();
        $conditions = $this->buildFilterSubQuery($qb, $filters, $parameters);
        $qb->select("ent")
            ->from($entityClass, "ent")
            ->where($conditions)
            ->setParameters($parameters);
        $data = $qb->getQuery()->getResult();
        return $data;
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
        $form->submit($postData, false);

        if ($form->isValid()) {
            $this->em->persist($entity);
            $this->em->flush();

            return $entity;
        } else {
            $this->handleFormError($form);
            return $form;
        }

    }

    function handleFormError($form)
    {
        $data = $this->get('qurep_api.form_error_serializer')->serializeFormErrors($form);
        throw new BadRequestHttpException($data);
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
            $this->handleFormError($form);
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