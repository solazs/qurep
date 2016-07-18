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
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Solazs\QuReP\ApiBundle\Resources\Consts;
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
    protected $entityParser;
    protected $filters;

    function __construct(
        EntityManager $entityManager,
        EntityFormBuilder $entityFormBuilder,
        FormErrorsSerializer $formErrorsHandler,
        EntityParser $entityParser
    ) {
        $this->em = $entityManager;
        $this->entityFormBuilder = $entityFormBuilder;
        $this->formErrorsHandler = $formErrorsHandler;
        $this->entityParser = $entityParser;
        $this->filters = [];
    }

    function getAll(string $entityClass)
    {
        $parameters = [];
        $qb = $this->em->createQueryBuilder();
        $conditions = $this->buildFilterSubQuery($qb, $parameters);
        $qb->select($this->buildPropString($entityClass))
            ->from($entityClass, 'ent');
        $this->buildJoinSubQuery($qb);
        if (count($conditions) > 0) {
            $qb->where($conditions)
                ->setParameters($parameters);
        }

        $data = $qb->getQuery()->getResult();

        return $data;
    }

    protected function buildJoinSubQuery(QueryBuilder &$qb)
    {
        foreach ($this->filters as $filterGrp) {
            foreach ($filterGrp as $filter) {
                $this->walkJoinFilterProp($filter['prop'], $qb);
            }
        }
    }

    private function walkJoinFilterProp(array $prop, QueryBuilder &$qb, $joinCntr = 0)
    {
        if ($prop['children'] != null) {
            $qb->leftJoin('ent' . ($joinCntr == 0 ? '' : $joinCntr) . '.' . $prop['name'], 'ent' . ++$joinCntr);
            $this->walkJoinFilterProp($prop['children'], $qb, $joinCntr);
        }
    }

    protected function buildFilterSubQuery(QueryBuilder $qb, array &$parameters)
    {
        $conditions = [];
        foreach ($this->filters as $filterGrp) {
            $andConditions = [];
            foreach ($filterGrp as $filter) {
                $propName = $this->walkFilterProp($filter["prop"]);
                $andConditions[] = call_user_func_array([$qb->expr(), $filter['operand']],
                    [$propName, ':' . str_replace('.', '_', $propName) . '_param']);
                $parameters[':' . str_replace('.', '_', $propName) . '_param'] = $filter['value'];
            }
            if (count($andConditions) > 0) {
                if (count($andConditions) > 1) {
                    $andConditions = call_user_func_array([$qb->expr(), 'andx'], $andConditions);
                    $conditions[] = $andConditions;
                } else {
                    $conditions[] = $andConditions[0];
                }
            }
        }
        if (count($conditions) > 0) {
            if (count($conditions) > 1) {
                $conditions = call_user_func_array([$qb->expr(), 'orx'], $conditions);
            } else {
                $conditions = $conditions[0];
            }
        }

        return $conditions;
    }

    private function walkFilterProp(array $prop, $joinCntr = 0)
    {
        if ($prop['children'] != null) {
            return $this->walkFilterProp($prop['children'], ++$joinCntr);
        } else {
            return 'ent' . ($joinCntr == 0 ? '' : $joinCntr) . '.' . $prop['name'];
        }
    }

    protected function buildPropString(string $entityClass) : string
    {
        $propString = 'ent.id';
        $props = $this->entityParser->getProps($entityClass);
        foreach ($props as $prop) {
            if ($prop['propType'] == Consts::prop || $prop['propType'] == Consts::formProp) {
                $propString = $propString . ', ent.' . $prop['name'];
            }
        }

        return $propString;
    }

    function bulkUpdate(string $entityClass, array $postData = array())
    {
        if (!is_array($postData)) {
            throw new BadRequestHttpException('Data is not an array');
        }
        if (count($postData) == 0) {
            throw new BadRequestHttpException('Data is an empty array');
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

    function update(string $entityClass, $id = 0, array $postData = array())
    {
        if (count($postData) == 0) {
            throw new BadRequestHttpException('No data supplied');
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

    function get(string $entityClass, int $id = 0)
    {
        $qb = $this->em->createQueryBuilder()
            ->select($this->buildPropString($entityClass))
            ->from($entityClass, 'ent');
        $qb->where('ent.id = :id')
            ->setParameter(':id', $id);
        $data = $qb->getQuery()
            ->getOneOrNullResult();

        if (!$data) {
            throw new NotFoundHttpException('Entity with id ' . $id . ' was not found in the database.');
        }

        return $data;
    }

    function handleFormError($form)
    {
        $data = $this->formErrorsHandler->serializeFormErrors($form, true);
        throw new FormErrorException($data);
    }

    function create(string $entityClass, array $postData = array())
    {
        if (count($postData) == 0) {
            throw new BadRequestHttpException('No data supplied');
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

    /**
     * @param array $filters
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
    }
}