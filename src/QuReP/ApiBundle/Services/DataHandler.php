<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.12.10.
 * Time: 20:51
 */

namespace QuReP\ApiBundle\Services;


use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DataHandler
{
    /* @var $em \Doctrine\ORM\EntityManager */
    protected $em;

    function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    function get($entityClass, $id = 0)
    {
        $data = $this->em->getRepository($entityClass)->find($id);
        if (!$data)
        {
            throw new NotFoundHttpException('Entity with id ' . $id . ' was not found in the database.');
        }
        return $data;
    }

    function getAll($entityClass)
    {
        return $this->em->getRepository($entityClass)->findAll();
    }

    function delete($entityClass, $id = 0)
    {
        $entity = $this->em->getRepository($entityClass)->find($id);
        if (!$entity){
            throw new NotFoundHttpException('Entity with id ' . $id . ' was not found in the database.');
        }
        $this->em->remove($entity);
        $this->em->flush();
    }

    function deleteCollection($entityClass, Request $request)
    {
        if ($content = $request->getContent()){
            throw new BadRequestHttpException('no "data" object found in content', 400);
        } else {
            $data = json_decode($content, true);
            if (count($data) === 0){
                throw new BadRequestHttpException('"data" has no elements', 400);
            }
            $this->em->createQueryBuilder()
                ->delete($entityClass)
                ->where('id IN :ids')
                ->setParameter(':ids', $data)
                ->getQuery()
                ->execute();
        }
    }
}