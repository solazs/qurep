<?php
namespace Solazs\QuReP\ApiBundle\Serializer;

use JMS\Serializer\SerializationContext;

class QuRePSerializationContext extends SerializationContext
{

    public function isVisiting($object)
    {
        return false;
    }
}
