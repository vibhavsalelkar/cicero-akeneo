<?php


namespace Webkul\Magento2Bundle\Component\Validator;

use Symfony\Component\Validator\Constraint;

class ValidCredentials extends Constraint
{
    const INVALID_CREDENTIAL = 'c1051bb4-d103-4f74-8988-acbcafc7fdc3';

    protected static $errorNames = array(
        self::INVALID_CREDENTIAL => 'INVALID_CREDENTIAL',
    );

    public $message = 'Credentials are invalid.';
    public $message2 = 'StoreView Mapping is invalid.';
}
