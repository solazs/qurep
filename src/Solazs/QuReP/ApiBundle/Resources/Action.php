<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.17.
 * Time: 0:08
 */

namespace Solazs\QuReP\ApiBundle\Resources;


abstract class Action
{
    const GET_SINGLE = 0;
    const GET_COLLECTION = 1;
    const POST_SINGLE = 2;
    const POST_COLLECTION = 3;
    const UPDATE_SINGLE = 4;
    const UPDATE_COLLECTION = 5;
    const DELETE_SINGLE = 6;
    const DELETE_COLLECTION = 7;
}