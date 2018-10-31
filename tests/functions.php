<?php

/*
 * This file is part of the magerun2-password-normalizer package.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Helper function for the unit tests to simulate Magento's translation function.
 *
 * @param $string
 * @return \Magento\Framework\Phrase
 */
function __($string)
{
    return new \Magento\Framework\Phrase($string);
}
