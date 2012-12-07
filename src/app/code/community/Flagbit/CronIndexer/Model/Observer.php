<?php
/**
 * Created by JetBrains PhpStorm.
 * User: weller
 * Date: 06.12.12
 * Time: 18:05
 * To change this template use File | Settings | File Templates.
 */

class Flagbit_CronIndexer_Model_Observer {


    public function process($schedule)
    {
        /* @var $indexer Mage_Index_Model_Indexer */
        $indexer    = Mage::getSingleton('index/indexer');
        $processIds = explode(',', $schedule->getMessages());

        foreach ($processIds as $processId) {
            /* @var $process Mage_Index_Model_Process */
            $process = $indexer->getProcessById($processId);
            if ($process) {
                $process->reindexEverything();
            }
        }
    }


    /**
     * Prepare the adminhtml category products view
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function adminhtmlBlockHtmlBefore(Varien_Event_Observer $observer)
    {
        /* @var $block Mage_Adminhtml_Block_Widget_Grid */
        $block = $observer->getBlock();

        if ($block instanceof Mage_Index_Block_Adminhtml_Process_Grid
            && $block->getId() == 'indexer_processes_grid'
        ) {
            $block->addColumn(
                'dynamic',
                array(
                    'header'         => Mage::helper('flagbit_cronindexer')->__('Schedule'),
                    'width'          => '80',
                    'index'          => 'schedule',
                    'sortable'       => false,
                    'filter'         => false,
                    'frame_callback' => array($this, 'decorateType')
                )
            );

            $block->getMassactionBlock()->addItem('flagbit_cronindexer', array(
                'label'    => Mage::helper('flagbit_cronindexer')->__('Reindex Data (Cronjob)'),
                'url'      => $block->getUrl('*/*/massReindexCron'),
                'selected' => true,
            ));
        }
    }

    /**
     * Decorate Type column values
     *
     * @return string
     */
    public function decorateType($value, $row, $column, $isExport)
    {

        /* @var $collection Mage_Cron_Model_Resource_Schedule_Collection */
        $collection = Mage::getModel('cron/schedule')->getCollection();
        $collection->addFieldToFilter('job_code', array('eg' => 'flagbit_cronindexer'))
                   ->setOrder('scheduled_at', 'DESC');

        $schedule = $collection->getFirstItem();

        $class = 'grid-severity-notice';
        $value = $column->getGrid()->__('nothing planned');

        if (in_array($row->getId(), explode(',', $schedule->getMessages()))) {

            $scheduledAtTimestamp = strtotime($schedule->getScheduledAt());
            $executedAtTimestamp = strtotime($schedule->getExecutedAt());
            $finishedAtTimestamp = strtotime($schedule->getFinishedAt());


            // will be executed in the future
            if($scheduledAtTimestamp > time()){
                $class = 'grid-severity-minor';
                $value = $column->getGrid()->__('pending (%s minutes)', round(($scheduledAtTimestamp - time())/60, 0));

            // will be executed in the future
            }elseif($scheduledAtTimestamp < time() && !$schedule->getExecutedAt()){
                $class = 'grid-severity-major';
                $value = $column->getGrid()->__('pending (%s minutes)', round(($scheduledAtTimestamp - time())/60, 0));

            // is running
            }elseif($scheduledAtTimestamp < time()
                && $schedule->getExecutedAt() && $executedAtTimestamp < time()
                && !$schedule->getFinishedAt()){
                $class = 'grid-severity-minor';
                $value = $column->getGrid()->__('running (%s minutes)', round((time() - $executedAtTimestamp) /60, 0));

            // was executed
            }elseif($scheduledAtTimestamp < time()
                && $schedule->getExecutedAt() && $executedAtTimestamp < time()
                && $schedule->getFinishedAt()){
                $class = 'grid-severity-notice';
                $value = $column->getGrid()->__('finished %s', Mage::helper('core')->formatDate($schedule->getFinishedAt(), Mage_Core_Model_Locale::FORMAT_TYPE_SHORT, true));
            }
       }

        return '<span class="'.$class.'"><span>'.$value.'</span></span>';

    }

}