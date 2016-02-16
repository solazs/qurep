<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.12.10.
 * Time: 20:51
 */

namespace Solazs\QuReP\ApiBundle\Services;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Internal\Hydration\ArrayHydrator;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Solazs\QuReP\ApiBundle\Exception\FormErrorException;

class DataHandler
{
    /* @var $em \Doctrine\ORM\EntityManager */
    protected $em;
    protected $entityFormBuilder;
    protected $formErrorsHandler;

    function __construct(
        EntityManager $entityManager,
        EntityFormBuilder $entityFormBuilder,
        FormErrorsSerializer $formErrorsHandler
    )
    {
        $this->em = $entityManager;
        $this->entityFormBuilder = $entityFormBuilder;
        $this->formErrorsHandler = $formErrorsHandler;
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
            if (count($andConditions) > 1) {
                $andConditions = call_user_func_array([$qb->expr(), "andx"], $andConditions);
                $conditions[] = $andConditions;
            }
        }
        if (count($conditions) > 1) {
            $conditions = call_user_func_array(array($qb->expr(), "orx"), $conditions);
        }

        return $conditions;
    }

    function get(string $entityClass, int $id = 0)
    {
        $data = $this->em->createQueryBuilder()
            ->select('ent')
            ->from($entityClass, 'ent')
            ->where('ent.id = :id')
            ->setParameter(':id', $id)
            ->getQuery()
            ->getSingleResult();

        if (!$data) {
            throw new NotFoundHttpException('Entity with id ' . $id . ' was not found in the database.');
        }
        return $data;
    }

    function getAll(string $entityClass, $filters = array())
    {
        $parameters = [];
        $qb = $this->em->createQueryBuilder();
        $conditions = $this->buildFilterSubQuery($qb, $filters, $parameters);
        $qb->select("ent")
            ->from($entityClass, "ent");
        if (count($conditions) > 0) {
            $qb->where($conditions)
                ->setParameters($parameters);
        }

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

            return $this->get($entityClass, $entity->getId());
        } else {
            $this->handleFormError($form);
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
        $returnData = [];
        foreach ($postData as $item) {
            if (array_key_exists('id', $item)) {
                $data = $item;
                unset($data['id']);
                $entity = $this->update($entityClass, $item['id'], $data);
            } else {
                $entity = $this->create($entityClass, $item);
            }
            $returnData[] = $entity;
        }

        return $returnData;
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

            return $this->get($entityClass, $entity->getId());
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
            ->delete($entityClass, 'ent')
            ->where('ent.id IN (:ids)')
            ->setParameter(':ids', $ids)
            ->getQuery()
            ->execute();
    }

    function handleFormError($form)
    {
        $data = $this->formErrorsHandler->serializeFormErrors($form, true);
        throw new FormErrorException($data);
    }
}