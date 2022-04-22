<?php

namespace Webkul\Magento2Bundle\Component\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Webkul\Magento2Bundle\Component\Validator\ValidJobCredentials;

/**
 * @author webkul
 */
class ValidJobCredentialsValidator extends ConstraintValidator
{
    public function validate($singleValue, Constraint $constraint= null)
    {
        if ($singleValue == 0) {
            $this->context->buildViolation($constraint->message)
            ->addViolation();
        }
        
        return $singleValue;
    }
}
