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
    const validOperands = ['isnull', 'isnotnull', 'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'like', 'notlike'];
    const qurepLogLabel = '[QuReP] ';
}