<?php

class Flagbit_CronIndexer_Adminhtml_ProcessController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Schedule Mass rebuild selected processes index
     *
     */
    public function massReindexCronAction()
    {

        /* @var $indexer Mage_Index_Model_Indexer */
        $indexer    = Mage::getSingleton('index/indexer');
        $processIds = $this->getRequest()->getParam('process');

        if (empty($processIds) || !is_array($processIds)) {
            $this->_getSession()->addError(Mage::helper('flagbit_cronindexer')->__('Please select Indexes'));
        } else {
            try {

                $scheduleAheadFor = Mage::getStoreConfig(Mage_Cron_Model_Observer::XML_PATH_SCHEDULE_AHEAD_FOR)*60;
                $schedule = Mage::getModel('cron/schedule');
                $jobCode = 'flagbit_cronindexer';
                $now = time()+60;
                $timeAhead = $now + $scheduleAheadFor;


                $schedules = Mage::getModel('cron/schedule')->getCollection()
                                ->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_PENDING)
                                ->load();

                $exists = array();
                foreach ($schedules->getIterator() as $schedule) {
                    $exists[$schedule->getJobCode()] = 1;
                }

                $schedule->setJobCode($jobCode)
                    ->setCronExpr('* * * * *')
                    ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
                    ->setMessages(implode(',', $processIds));

                $_errorMsg = null;
                for ($time = $now; $time < $timeAhead; $time += 60) {
                    if (!empty($exists[$jobCode])) {
                        $_errorMsg = Mage::helper('flagbit_cronindexer')->__('There are already Index(es) scheduled, please try again later.');
                        continue;
                    }
                    if (!$schedule->trySchedule($time)) {
                        // time does not match cron expression
                        $_errorMsg = Mage::helper('flagbit_cronindexer')->__('Something went wrong, please try again later.');
                        continue;
                    }
                    $_errorMsg = null;
                    $schedule->unsScheduleId()->save();
                    break;
                }
                if($_errorMsg !== NULL){
                    $this->_getSession()->addError($_errorMsg);
                }else{
                    $count = count($processIds);
                    $this->_getSession()->addSuccess(
                        Mage::helper('flagbit_cronindexer')->__('Total of %d index(es) are scheduled.', $count)
                    );
                }

            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addException($e, Mage::helper('flagbit_cronindexer')->__('Cannot initialize the indexer process.'));
            }
        }

        $this->_redirect('*/*/list');
    }

    /**
     * Check ACL permissins
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/index');
    }
}
