<?php

namespace Webkul\Magento2Bundle\Traits;

/**
* step execution trait used for filtering and so
*/
trait StepExecutionTrait
{
    /**
    * get channel scope from stepExecution JobParameters.
    * @param StepExecution $stepExecution
    * @return string $scope
    */
    protected function getChannelScope(\StepExecution $stepExecution)
    {
        $parameters = $this->stepExecution->getJobParameters();
        $filters = $parameters->get('filters');
        if ($this->isQuickExport($parameters)) {
            $scope = $filters[0]['context']['scope'];
        } else {
            $scope = !empty($filters['structure']['scope']) ? $filters['structure']['scope'] : null;
        }

        return $scope;
    }

    /**
     * get locales from stepExecution JobParameters.
     * @param StepExecution $stepExecution
     * @return array $locales
     */
    protected function getFilterLocales(\StepExecution $stepExecution)
    {
        $parameters = $this->stepExecution->getJobParameters();

        return $this->getFilterLocalesByParameters($parameters);
    }

    /**
     * get locales from JobParameters for both normalJobExport and quickJobExport
     * @param StepExecution $stepExecution
     * @return array $locales
     */
    protected function getFilterLocalesByParameters(\JobParameters $parameters)
    {
        $filters = $parameters->get('filters');
        if ($this->isQuickExport($parameters)) {
            $locale = $filters[0]['context']['locale'];
            $locales = [ $locale ];
        } else {
            $locales = !empty($filters['structure']['locales']) ? $filters['structure']['locales'] : [];
        }

        return $locales;
    }

    /**
     * check if job is quick export?
     * @param JobParameters $parameters
     * @return boolean isQuickExport
     */
    protected function isQuickExport(\JobParameters $parameters)
    {
        $filters = $parameters->get('filters');

        return !empty($filters[0]['context']);
    }
}
