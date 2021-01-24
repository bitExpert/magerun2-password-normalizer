<?php

/*
 * This file is part of the magerun2-password-normalizer package.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BitExpert\Magento\PasswordNormalizer\Command;

class SqlHelper
{
    public const ID_PLACEHOLDER = '(ID)';

    /**
     * Construct manual DB query, because Magento2 is stupid and doesn't have good iterator or bulk-actions
     *
     * @param string $mailMask
     * @param string $passwordHash
     * @return string
     */
    public function buildSql(string $mailMask, string $passwordHash): string
    {
        // convert the email-mask input to SQL
        $mailMask = str_replace(
            self::ID_PLACEHOLDER,
            "',entity_id,'",
            $mailMask
        );

        $sql = sprintf(
            "UPDATE customer_entity SET email = CONCAT('%s'), password_hash = '%s'",
            $mailMask,
            $passwordHash
        );

        return $sql;
    }

    /**
     * Appends the where clauses to the SQL based on the $excludedEmails
     *
     * @param string $sql
     * @param string $excludedEmails
     * @return string
     */
    public function appendSqlWhereClause(string $sql, string $excludedEmails = null): string
    {
        if (isset($excludedEmails) && !empty($excludedEmails)) {
            $excludedEmailsArr = explode(';', $excludedEmails);
            $concated = implode("' AND email NOT LIKE '", $excludedEmailsArr);
            $sql = sprintf(
                "%s WHERE email NOT LIKE '%s'",
                $sql,
                $concated
            );
        }

        return $sql;
    }
}
