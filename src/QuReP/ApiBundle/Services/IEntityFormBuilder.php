<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.01.08.
 * Time: 1:25
 */

namespace QuReP\ApiBundle\Services;


use Symfony\Component\Form\Form;

interface IEntityFormBuilder
{
    public function getForm(string $entityClass) : Form;
}