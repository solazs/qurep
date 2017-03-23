<?php
/**
 * Created by PhpStorm.
 * User: solazs
 * Date: 2017.03.22.
 * Time: 15:24
 */

namespace Solazs\QuReP\ApiBundle\Resources;


abstract class PropType
{
    const PROP = 'property';
    const TYPED_PROP = 'typedProperty';
    const SINGLE_PROP = 'singularRelation';
    const PLURAL_PROP = 'pluralRelation';
}