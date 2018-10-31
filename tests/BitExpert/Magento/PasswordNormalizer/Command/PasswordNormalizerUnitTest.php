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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
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
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->statement = $this->createMock(Zend_Db_Statement_Interface::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->application = new Application();
        $this->application->init([], $this->input, $this->output);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connection);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
    }

    /**
     * @test
     */
    public function missingPasswordParameterThrowsException()
    {
        self::expectException(LocalizedException::class);

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
            ->will(
                $this->returnValueMap([
                    [PasswordNormalizer::OPTION_PASSWORD, 'random-password-to-set'],
                    [PasswordNormalizer::OPTION_EMAIL_MASK, 'customer_(ID)@example.com'],
                    [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, ''],
                ])
            );

        $this->connection->expects($this->any())
            ->method('query')
            ->willReturn($this->statement);

        // when everything is working fine, writeln() will be the last operation of the command. Thus, if the method
        // is called we can assume that the execution went well.
        $this->output->expects($this->exactly(1))
            ->method('writeln');

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
            ->will(
                $this->returnValueMap([
                    [PasswordNormalizer::OPTION_PASSWORD, 'random-password-to-set'],
                    [PasswordNormalizer::OPTION_EMAIL_MASK, 'customer_(ID)@example.com'],
                    [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, ''],
                ])
            );

        $this->encryptor->expects($this->once())
            ->method('getHash')
            ->with(
                $this->equalTo('random-password-to-set'),
                $this->equalTo(true)
            )
            ->will($this->returnValue('encrypted-random-password'));

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
            ->will(
                $this->returnValueMap([
                    [PasswordNormalizer::OPTION_PASSWORD, ''],
                    [PasswordNormalizer::OPTION_EMAIL_MASK, 'some-mask-without-placeholder'],
                    [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, ''],
                ])
            );

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
            ->will(
                $this->returnValueMap([
                    [PasswordNormalizer::OPTION_PASSWORD, 'random-password-to-set'],
                    [PasswordNormalizer::OPTION_EMAIL_MASK, 'customer_(ID)@example.com'],
                    [PasswordNormalizer::OPTION_EXCLUDE_EMAILS, 'bitexpert.de'],
                ])
            );

        $this->encryptor->expects($this->once())
            ->method('getHash')
            ->with(
                $this->equalTo('random-password-to-set'),
                $this->equalTo(true)
            )
            ->will($this->returnValue('encrypted-random-password'));

        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(function ($sql) {
                    return strpos($sql, 'encrypted-random-password') > 0 &&
                        strpos($sql, 'bitexpert.de') > 0;
                })
            )
            ->willReturn($this->statement);

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
            ->setMethods(['getResource', 'getEncryptor'])
            ->getMock();
        $command->method('getResource')
            ->willReturn($this->resourceConnection);
        $command->method('getEncryptor')
            ->willReturn($this->encryptor);
        return $command;
    }
}
