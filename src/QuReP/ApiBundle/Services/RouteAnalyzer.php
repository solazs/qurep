<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.15.
 * Time: 21:27
 */

namespace QuReP\ApiBundle\Services;


class RouteAnalyzer
{
    protected $entities;

    public function setConfig($entities){
        $this->entities = $entities;
    }
}