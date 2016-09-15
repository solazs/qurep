<?php

namespace Solazs\QuReP\ApiBundle\Resources;

/**
 * Class Consts
 *
 * Miscellaneous constants
 *
 * @package Solazs\QuReP\ApiBundle\Resources
 */
abstract class Consts
{
    const prop = 'prop';
    const formProp = 'formProp';
    const singleProp = 'singleProp';
    const pluralProp = 'pluralProp';
    const validOperands = ['isnull', 'isnotnull', 'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'like', 'notlike'];
}