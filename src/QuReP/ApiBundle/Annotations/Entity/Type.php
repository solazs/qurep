<?php
/**
 * Created by PhpStorm.
 * User: solazs
 * Date: 2015.12.22.
 * Time: 20:26
 *
 * This class is an annotation to be used in Entity classes to mark the type of the properties.
 * For available types see http://symfony.com/doc/current/reference/forms/types.html
 */

namespace QuReP\ApiBundle\Annotations\Entity;


use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\AnnotationException;

/**
 * @Annotation
 * @Target("METHOD")
 */
class Type
{
    private $type;
    public function __construct($type)
    {
        $this->type = $type;
        if (!class_exists($type)){
            throw new AnnotationException('Class ' . $type . ' does not exists.');
        }
    }

    public function getType()
    {
        return $this->type;
    }
}