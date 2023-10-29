<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Cron;

use CembraPayCheckout\CembraPayCheckoutCore\Helper\DataHelper;

class CembraCheckoutCron
{
    private $dataHelper;
    public function __construct(DataHelper $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    public function execute()
    {
        $this->dataHelper->getPendingOrders();
        return;
        $debug = var_export(get_class($this->dataHelper), true);
        file_put_contents("/tmp/last_test.txt", $debug);
        return $this;
    }
}
