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
use Doctrine\Common\Annotations\AnnotationException;

/**
 * @Annotation
 * @Annotation\Target("PROPERTY")
 */
class Type
{
    /** @var string
     *  @Annotation\Required()
     */
    private $type;
    /** @var array */
    private $options;
    /** @var string */
    private $label;
    public function __construct($values)
    {
        $this->type = $values['type'];
        if (!class_exists($this->type)){
            throw new AnnotationException('Class ' . $this->type . ' does not exists.');
        }
        if (array_key_exists('options', $values)) {
            $this->options = $values['options'];
        } else {
            $this->options = null;
        }
        if (array_key_exists('label', $values)) {
            $this->label = $values['label'];
        } else {
            $this->label = null;
        }
    }

    /**
     * @return string
     */
    public function getType() : string
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getOptions() : array
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function getLabel() : string
    {
        return $this->label;
    }
}