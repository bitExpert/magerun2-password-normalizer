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

use Magento\Customer\Model\Customer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Indexer\Model\Indexer;
use N98\Magento\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Db_Statement_Interface;

class SqlHelperUnitTest extends TestCase
{
    /**
     * @var SqlHelper
     */
    private SqlHelper $sqlHelper;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->sqlHelper = new SqlHelper();
    }

    /**
     * @test
     */
    public function buildSqlWithMailMaskAndPasswordHash(): void
    {
        $mailMask = 'c_' . SqlHelper::ID_PLACEHOLDER . '@example.com';
        $passwordHash = '12345';
        $expected = "UPDATE customer_entity SET email = CONCAT('c_',entity_id,'@example.com'), password_hash = '12345'";

        $actual = $this->sqlHelper->buildSql($mailMask, $passwordHash);

        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function appendSqlWhereClauseWithEmptyString(): void
    {
        $expected = 'SELECT * FROM my_awesome_table';

        $actual = $this->sqlHelper->appendSqlWhereClause($expected, '');

        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function appendSqlWhereClauseWithOneEmail(): void
    {
        $exampleSql = 'SELECT * FROM my_awesome_table';
        $mails = 'foo@example.com';
        $expected = 'SELECT * FROM my_awesome_table WHERE email NOT LIKE \'foo@example.com\'';

        $actual = $this->sqlHelper->appendSqlWhereClause($exampleSql, $mails);

        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function appendSqlWhereClauseWithTwoEmail(): void
    {
        $exampleSql = 'SELECT * FROM my_awesome_table';
        $mails = 'foo@example.com;bar@example.com';
        $expected = 'SELECT * FROM my_awesome_table WHERE email NOT LIKE \'foo@example.com\' AND '.
            'email NOT LIKE \'bar@example.com\'';

        $actual = $this->sqlHelper->appendSqlWhereClause($exampleSql, $mails);

        self::assertEquals($expected, $actual);
    }
}
