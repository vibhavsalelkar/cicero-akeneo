<?php

namespace Webkul\ImageGalleryBundle\Saver;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class BaseSaver implements \SaverInterface, \BulkSaverInterface
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var string */
    protected $savedClass;

    /**
     * @param ObjectManager                  $objectManager
     * @param EventDispatcherInterface       $eventDispatcher
     * @param string                         $savedClass
     */
    public function __construct(
        ObjectManager $objectManager,
        EventDispatcherInterface $eventDispatcher,
        $savedClass
    ) {
        $this->objectManager = $objectManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->savedClass = $savedClass;
    }

    /**
     * {@inheritdoc}
     */
    public function save($object, array $options = [])
    {
        $this->validateObject($object);
        $options['unitary'] = true;
        $options['is_new'] = null === $object->getId();
        // $this->eventDispatcher->dispatch(StorageEvents::PRE_SAVE, new GenericEvent($object, $options));

        $this->objectManager->persist($object);
        $this->objectManager->flush();

        $this->eventDispatcher->dispatch(\StorageEvents::POST_SAVE, new GenericEvent($object, $options));
    }

    /**
     * {@inheritdoc}
     */
    public function saveAll(array $objects, array $options = [])
    {
        if (empty($objects)) {
            return;
        }
        $options['unitary'] = false;

        $this->eventDispatcher->dispatch(\StorageEvents::PRE_SAVE_ALL, new GenericEvent($objects, $options));

        $areObjectsNew = array_map(function ($object) {
            return null === $object->getId();
        }, $objects);

        foreach ($objects as $i => $object) {
            if (empty($object->getCode())) {
                continue;
            }

            $this->validateObject($object);

            $this->eventDispatcher->dispatch(
                \StorageEvents::PRE_SAVE,
                new GenericEvent($object, array_merge($options, ['is_new' => $areObjectsNew[$i]]))
            );

            $this->objectManager->persist($object);
        }

        $this->objectManager->flush();

        foreach ($objects as $i => $object) {
            $this->eventDispatcher->dispatch(
                \StorageEvents::POST_SAVE,
                new GenericEvent($object, array_merge($options, ['is_new' => $areObjectsNew[$i]]))
            );
        }

        $this->eventDispatcher->dispatch(\StorageEvents::POST_SAVE_ALL, new GenericEvent($objects, $options));
    }

    /**
     * @param $object
     */
    protected function validateObject($object)
    {
        if (!$object instanceof $this->savedClass) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expects a "%s", "%s" provided.',
                    $this->savedClass,
                    ClassUtils::getClass($object)
                )
            );
        }
    }
}
