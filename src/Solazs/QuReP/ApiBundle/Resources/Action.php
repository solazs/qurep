<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.17.
 * Time: 0:08
 */

namespace Solazs\QuReP\ApiBundle\Resources;

/**
 * Class Action
 *
 * Contains constants for actions
 *
 * @package Solazs\QuReP\ApiBundle\Resources
 */
abstract class Action
{
    const GET_SINGLE = 'GET_SINGLE';
    const GET_COLLECTION = 'GET_COLLECTION';
    const POST_SINGLE = 'POST_SINGLE';
    const POST_COLLECTION = 'POST_COLLECTION';
    const UPDATE_SINGLE = 'UPDATE_SINGLE';
    const UPDATE_COLLECTION = 'UPDATE_COLLECTION';
    const DELETE_SINGLE = 'DELETE_SINGLE';
    const DELETE_COLLECTION = 'DELETE_COLLECTION';
}