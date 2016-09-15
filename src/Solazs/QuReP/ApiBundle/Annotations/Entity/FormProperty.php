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

namespace Solazs\QuReP\ApiBundle\Annotations\Entity;


use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationException;

/**
 * Annotation for properties of entities handled by QuReP.
 *
 * Type parameter should be one of the Symfony FormTypes,
 * for more see @link [http://symfony.com/doc/current/reference/forms/types.html]
 *
 * Options is the options array for the form type
 *
 * Label can be used to overwrite property names on the API.
 *
 * @Annotation
 * @Annotation\Target("PROPERTY")
 */
class FormProperty
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
        if (!array_key_exists('type', $values)) {
            throw new AnnotationException('type property is required for the FormProperty Annotation');
        }
        $this->type = $values['type'];
        if (!class_exists('\Symfony\Component\Form\Extension\Core\Type\\'.$this->type)) {
            throw new AnnotationException('Class '.$this->type.' does not exists.');
        } else {
            $this->type = '\Symfony\Component\Form\Extension\Core\Type\\'.$this->type;
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