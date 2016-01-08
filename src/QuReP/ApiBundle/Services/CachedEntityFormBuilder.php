<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.01.08.
 * Time: 1:02
 */

namespace QuReP\ApiBundle\Services;


use Doctrine\Common\Cache\ApcuCache;
use Symfony\Component\Form\Form;

class CachedEntityFormBuilder implements IEntityFormBuilder
{
    protected $entityFormBuilder;
    protected $cache;

    public function __construct(EntityFormBuilder $entityFormBuilder, ApcuCache $apcuCache)
    {
        $this->entityFormBuilder = $entityFormBuilder;
        $this->cache = $apcuCache;
    }

    public function getForm($entityClass)
    {
        if ($this->cache->contains($entityClass)) {
            return clone($this->cache->fetch($entityClass));
        } else {
            $form = $this->entityFormBuilder->getForm($entityClass);
            $this->cache->save($entityClass, $form);
            return clone($form);
        }
    }
}