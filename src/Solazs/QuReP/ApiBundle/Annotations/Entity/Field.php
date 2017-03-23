<?php

namespace Solazs\QuReP\ApiBundle\Annotations\Entity;


use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationException;

/**
 * This annotation is used to mark a property of an entity to be processed by QuReP.
 *
 * Only properties with a type supplied shall be POSTed to the API. This is because QuReP relies extensively on Symfony Forms
 * for updating/creating resources. Forms will be generated on the fly using this type.
 *
 * @property string type
 * Should be one of the Symfony FormTypes (e.g. TextType for strings, or CheckboxType for boolean values),
 * for more see @link [http://symfony.com/doc/current/reference/forms/types.html]
 *
 * @property array  options
 * The options array for the form type
 *
 * @property string label
 * Can be used to overwrite property names on the API.
 *
 * @Annotation
 * @Annotation\Target("PROPERTY")
 */
class Field
{
    /** @var string
     * @Annotation\Required()
     */
    private $type;
    /** @var array */
    private $options;
    /** @var string */
    private $label;

    public function __construct($values)
    {
        if (array_key_exists('type', $values)) {
            $this->type = $values['type'];
            if (!class_exists('\Symfony\Component\Form\Extension\Core\Type\\'.$this->type)) {
                throw new AnnotationException('Class '.$this->type.' does not exists.');
            } else {
                $this->type = '\Symfony\Component\Form\Extension\Core\Type\\'.$this->type;
            }
        } else {
            $this->type = null;
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
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }
}