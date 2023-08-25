<?php

namespace CembraPayCheckout\CembraPayCheckoutCore\Cron;

use CembraPayCheckout\CembraPayCheckoutCore\Helper\DataHelper;

class CembraCheckoutCron
{

  //  protected $dataHelper;
    private $dataHelper;
    public function __construct(DataHelper $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    public function execute()
    {
        $debug = var_export(get_class($this->dataHelper), true);
        file_put_contents("/tmp/last_test.txt", $debug);
        return $this;
    }
}
