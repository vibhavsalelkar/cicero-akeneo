<?php


namespace Webkul\Magento2Bundle\Component\Validator;

use Symfony\Component\Validator\Constraint;

class ValidJobCredentials extends Constraint
{
    public $message = 'This field is required.';
}
