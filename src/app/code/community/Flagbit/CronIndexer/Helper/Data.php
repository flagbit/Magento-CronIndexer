<?php

class Flagbit_CronIndexer_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * is the new EE Indexer Module enabled?
     *
     * @return bool
     */
    public function isNewIndexerEnabled()
    {
        if($this->isModuleEnabled('Enterprise_Index')){
            return true;
        }
        return false;
    }

}
