<?php
/**
 * Created by PhpStorm.
 * User: solazs
 * Date: 2017.04.12.
 * Time: 13:43
 */

namespace Solazs\QuReP\ApiBundle\Serializer;


use Doctrine\Common\Annotations\AnnotationReader;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Naming\PropertyNamingStrategyInterface;
use Solazs\QuReP\ApiBundle\Annotations\Entity\Field;

class QuRePNamingStrategy implements PropertyNamingStrategyInterface
{
    private $separator;
    private $lowerCase;

    public function __construct($separator = '_', $lowerCase = true)
    {
        $this->separator = $separator;
        $this->lowerCase = $lowerCase;
    }

    /**
     * {@inheritDoc}
     */
    public function translateName(PropertyMetadata $property)
    {
        /** FIXME Reflection tends to be slow, we should use data from EntityParser  */
        $refClass = new \ReflectionClass($property->class);
        $refProp = $refClass->getProperty($property->name);


        $reader = new AnnotationReader();

        $name = null;

        foreach ($reader->getPropertyAnnotations($refProp) as $annotation) {
            if ($annotation instanceof Field) {
                if ($annotation->getLabel() !== null) {
                    $name = $annotation->getLabel();
                }
            }
        }

        if ($name === null) {
            $name = preg_replace('/[A-Z]/', $this->separator.'\\0', $property->name);

            if ($this->lowerCase) {
                return strtolower($name);
            }

            return ucfirst($name);
        } else {
            return $name;
        }
    }
}
