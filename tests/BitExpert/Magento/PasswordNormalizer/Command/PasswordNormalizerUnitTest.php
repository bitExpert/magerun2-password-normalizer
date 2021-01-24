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

class PasswordNormalizerUnitTest extends TestCase
{
    /**
     * @var InputInterface
     */
    private $input;
    /**
     * @var OutputInterface
     */
    private $output;
    /**
     * @var Application
     */
    private $application;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    /**
     * @var EncryptorInterface
     */
    private $encryptor;
    /**
     * @var Zend_Db_Statement_Interface
     */
    private $statement;
    /**
     * @var AdapterInterface
     */
    private $connection;
    /**
     * @var State
     */
    private $state;
    /**
     * @var Indexer
     */
    private $indexer;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->statement = $this->createMock(Zend_Db_Statement_Interface::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->indexer = $this->createMock(Indexer::class);
        $this->application = new Application();
        $this->application->init([], $this->input, $this->output);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connection);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->state = $this->createMock(State::class);
    }

    /**
     * @test
     */
    public function checkCommandConfiguration(): void
    {
        $command = $this->getPasswordNormalizerMock();
        $options = $command->getDefinition()->getOptions();
        $this->assertSame('dev:customer:normalize-passwords', $command->getName());
        $this->assertSame('Normalizes all customer-email-addresses and passwords', $command->getDescription());
        $this->assertCount(4, $options);
    }

    /**
     * @test
     */
    public function commandCannotBeRunInProductionMode()
    {
        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('This command can only be run in developer mode!');

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_PRODUCTION);

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function commandCannotBeRunInDefaultMode()
    {
        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('This command can only be run in developer mode!');

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_DEFAULT);

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function missingPasswordParameterThrowsException()
    {
        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('--password is a required option');

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_DEVELOPER);

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function emailMaskMissingPlaceholderThrowsException()
    {
        self::expectException(LocalizedException::class);
        self::expectExceptionMessage('--email-mask must contain (ID)');

        $this->input->expects($this->any())
            ->method('getOption')
            ->willReturnMap([
                [PasswordNormalizer::OPTION_PASSWORD, 'random-password-to-set'],
                [PasswordNormalizer::OPTION_EMAIL_MASK, 'customer@example.com'],
                [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, ''],
            ]);

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_DEVELOPER);


        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function passingRequiredPasswordParameterSucceeds()
    {
        $this->input->expects($this->any())
            ->method('getOption')
            ->willReturnMap([
                [PasswordNormalizer::OPTION_PASSWORD, 'random-password-to-set'],
                [PasswordNormalizer::OPTION_EMAIL_MASK, 'customer_(ID)@example.com'],
                [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, ''],
            ]);

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_DEVELOPER);

        $this->connection->expects($this->any())
            ->method('query')
            ->willReturn($this->statement);

        $this->encryptor->expects($this->any())
            ->method('getHash')
            ->willReturn('abcdef');

        // when everything is working fine, writeln() will be the last operation of the command. Thus, if the method
        // is called we can assume that the execution went well.
        $this->output->expects($this->exactly(3))
            ->method('writeln');

        $this->indexer->expects($this->once())
            ->method('reindexAll');

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function withAllRequiredOptionsProvidedTheSqlUpdateIsIssued()
    {
        $this->input->expects($this->any())
            ->method('getOption')
            ->willReturnMap([
                [PasswordNormalizer::OPTION_PASSWORD, 'random-password-to-set'],
                [PasswordNormalizer::OPTION_EMAIL_MASK, 'customer_(ID)@example.com'],
                [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, ''],
            ]);

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_DEVELOPER);

        $this->encryptor->expects($this->once())
            ->method('getHash')
            ->with(
                $this->equalTo('random-password-to-set'),
                $this->equalTo(true)
            )
            ->willReturn('encrypted-random-password');

        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(function ($sql) {
                    return strpos($sql, 'encrypted-random-password') > 0;
                })
            )
            ->willReturn($this->statement);

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function passingEmailMaskWithoutPlaceholderThrowsException()
    {
        self::expectException(LocalizedException::class);

        $this->input->expects($this->any())
            ->method('getOption')
            ->willReturnMap([
                [PasswordNormalizer::OPTION_PASSWORD, ''],
                [PasswordNormalizer::OPTION_EMAIL_MASK, 'some-mask-without-placeholder'],
                [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, ''],
            ]);

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_DEVELOPER);

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function withAllRequiredOptionsAndEmailMaskProvidedTheSqlUpdateIsIssued()
    {
        $this->input->expects($this->any())
            ->method('getOption')
            ->willReturnMap([
                [PasswordNormalizer::OPTION_PASSWORD, 'random-password-to-set'],
                [PasswordNormalizer::OPTION_EMAIL_MASK, 'customer_(ID)@example.com'],
                [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, 'bitexpert.de'],
            ]);

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_DEVELOPER);

        $this->encryptor->expects($this->once())
            ->method('getHash')
            ->with(
                $this->equalTo('random-password-to-set'),
                $this->equalTo(true)
            )
            ->willReturn('encrypted-random-password');

        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(function ($sql) {
                    return strpos($sql, 'encrypted-random-password') > 0 && strpos($sql, 'bitexpert.de') > 0;
                })
            )
            ->willReturn($this->statement);

        $this->indexer->expects($this->once())
            ->method('load')
            ->with(
                $this->equalTo(Customer::CUSTOMER_GRID_INDEXER_ID)
            );

        $this->indexer->expects($this->once())
            ->method('reindexAll');

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function passingForceParameterBypassModeCheck()
    {
        $this->input->expects($this->any())
            ->method('getOption')
            ->willReturnMap([
                [PasswordNormalizer::OPTION_PASSWORD, 'random-password-to-set'],
                [PasswordNormalizer::OPTION_EMAIL_MASK, 'customer_(ID)@example.com'],
                [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, 'bitexpert.de'],
                [PasswordNormalizer::OPTION_FORCE, true]
            ]);

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_DEVELOPER);

        $this->state->expects($this->never())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_PRODUCTION);

        $this->connection->expects($this->any())
            ->method('query')
            ->willReturn($this->statement);

        $this->encryptor->expects($this->any())
            ->method('getHash')
            ->willReturn('abcdef');

        // when everything is working fine, writeln() will be the last operation of the command. Thus, if the method
        // is called we can assume that the execution went well.
        $this->output->expects($this->exactly(3))
            ->method('writeln');

        $this->indexer->expects($this->once())->method('reindexAll');

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * @test
     */
    public function missingForceParameterBypassModeCheck()
    {
        self::expectException(LocalizedException::class);

        $this->input->expects($this->any())
            ->method('getOption')
            ->willReturnMap([
                [PasswordNormalizer::OPTION_FORCE, false]
            ]);

        $this->state->expects($this->any())
            ->method('getMode')
            ->willReturn(\Magento\Framework\App\State::MODE_PRODUCTION);

        /** @var PasswordNormalizer $command */
        $command = $this->getPasswordNormalizerMock();
        $command->setApplication($this->application);
        $command->run($this->input, $this->output);
    }

    /**
     * Helper method to configure a mocked version of
     * {@link \BitExpert\Magento\PasswordNormalizer\Command\PasswordNormalizer}.
     *
     * @return PasswordNormalizer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getPasswordNormalizerMock()
    {
        $command = $this->getMockBuilder(PasswordNormalizer::class)
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMethods(['getResource', 'getEncryptor', 'getState', 'getIndexer'])
            ->getMock();
        $command->method('getResource')
            ->willReturn($this->resourceConnection);
        $command->method('getEncryptor')
            ->willReturn($this->encryptor);
        $command->method('getState')
            ->willReturn($this->state);
        $command->method('getIndexer')
            ->willReturn($this->indexer);
        return $command;
    }
}
