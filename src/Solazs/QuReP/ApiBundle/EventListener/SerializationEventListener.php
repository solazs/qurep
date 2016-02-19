<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.02.11.
 * Time: 0:19
 */

namespace Solazs\QuReP\ApiBundle\EventListener;

use Doctrine\Common\Persistence\Proxy;
use Doctrine\ORM\PersistentCollection;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;


class SerializationEventListener implements EventSubscriberInterface
{
    public function onPreSerialize(PreSerializeEvent $event)
    {
        $object = $event->getObject();
        $type = $event->getType();

        // If the set type name is not an actual class, but a faked type for which a custom handler exists, we do not
        // modify it with this subscriber. Also, we forgo autoloading here as an instance of this type is already created,
        // so it must be loaded if its a real class.
        $virtualType = !class_exists($type['name'], false);

        if ($object instanceof PersistentCollection
            || $object instanceof MongoDBPersistentCollection
            || $object instanceof PHPCRPersistentCollection
        ) {

            if (!$virtualType) {
                $event->setType('ArrayCollection');
            }

            return;
        }

        if (!$object instanceof Proxy && !$object instanceof ORMProxy) {
            return;
        }

        //$object->__load();

        if (!$virtualType) {
            $event->setType(get_parent_class($object));

            //$event->setType('Solazs\QuReP\ApiBundle\Serializer\SerializerProxyType',
            //    ["id" => $object->getId()]);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            array('event' => 'serializer.pre_serialize', 'method' => 'onPreSerialize'),
        );
    }
}