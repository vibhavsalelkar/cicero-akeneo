<?php

namespace Webkul\Magento2Bundle\Extension\MassAction\Handler;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Translation\TranslatorInterface;
use Webkul\Magento2Bundle\Datasource\Orm\CustomObjectIdHydrator;
use Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\DeleteMassActionHandler;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvent;
use Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvents;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse;

/**
 * Mass delete products action handler
 *
 * @author    Webkul <support@webkul.com>
 *
 */
class DeleteDataMappingsMassActionHandler extends DeleteMassActionHandler
{
    public function __construct(
        \HydratorInterface $hydrator,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($hydrator, $translator, $eventDispatcher);
    }

    /**
     * {@inheritdoc}
     *
     * Dispatch two more events for data mapping
     */
    public function handle(DatagridInterface $datagrid, MassActionInterface $massAction)
    {
        // dispatch pre handler event
        $massActionEvent = new MassActionEvent($datagrid, $massAction, []);
        $this->eventDispatcher->dispatch(MassActionEvents::MASS_DELETE_PRE_HANDLER, $massActionEvent);

        $datasource = $datagrid->getDatasource();
        $datasource->setHydrator(new CustomObjectIdHydrator);

        $objectIds = $datasource->getResults();

        try {
            $this->eventDispatcher->dispatch('magento2.pre_mass_remove.data_mapping', new GenericEvent($objectIds));

            $countRemoved = $datasource->getMassActionRepository()->deleteFromIds($objectIds);

            $this->eventDispatcher->dispatch('magento2.post_mass_remove.data_mapping', new GenericEvent($objectIds));
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            return new MassActionResponse(false, $this->translator->trans($errorMessage));
        }

        // dispatch post handler event
        $massActionEvent = new MassActionEvent($datagrid, $massAction, $objectIds);
        $this->eventDispatcher->dispatch(MassActionEvents::MASS_DELETE_POST_HANDLER, $massActionEvent);

        return $this->getResponse($massAction, $countRemoved);
    }
}
