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
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Indexer\Model\Indexer;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PasswordNormalizer extends AbstractMagentoCommand
{
    public const OPTION_PASSWORD = 'password';
    public const OPTION_EXCLUDE_EMAILS = 'exclude-emails';
    public const OPTION_EMAIL_MASK = 'email-mask';
    public const OPTION_FORCE = 'force';
    /**
     * @var SqlHelper
     */
    private $sqlHelper;

    /**
     * PasswordNormalizer constructor.
     *
     * @param string|null $name
     */
    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->sqlHelper = new SqlHelper();
    }

    protected function configure(): void
    {
        $this
            ->setName('dev:customer:normalize-passwords')
            ->setDescription('Normalizes all customer-email-addresses and passwords')
            ->addOption(
                self::OPTION_PASSWORD,
                'p',
                InputOption::VALUE_REQUIRED,
                'Specify the desired password'
            )
            ->addOption(
                self::OPTION_EXCLUDE_EMAILS,
                'x',
                InputOption::VALUE_OPTIONAL,
                'Exclude email-addresses from being update by appending "WHERE email NOT LIKE ..."'.
                '(example: --exclude-emails %@bitexpert.%)' . PHP_EOL .
                '; separates multiple conditions (example: --exclude-emails %@bitexpert.%;%@gmail%)'
            )
            ->addOption(
                self::OPTION_EMAIL_MASK,
                'm',
                InputOption::VALUE_OPTIONAL,
                'Define the email-mask that is used to normalize the addresses. Must contain ' .
                SqlHelper::ID_PLACEHOLDER . '. Default: customer_' . SqlHelper::ID_PLACEHOLDER . '@example.com',
                'customer_' . SqlHelper::ID_PLACEHOLDER . '@example.com'
            )
            ->addOption(
                self::OPTION_FORCE,
                'f',
                InputOption::VALUE_NONE,
                'Performs actions even if the mode is not developer - USE WITH CAUTION!!!'
            );
    }

    /**
     * Updates customer_entity and sets the same password and email-address for
     * all users that do not match the exclude mask
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     * @throws LocalizedException
     * @throws \Zend_Db_Statement_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // check environment
        $force = (bool) $input->getOption(self::OPTION_FORCE);

        if (!($force || State::MODE_DEVELOPER === $this->getState()->getMode())) {
            throw new LocalizedException(__('This command can only be run in developer mode!'));
        }

        $excludedEmails = $input->getOption(self::OPTION_EXCLUDE_EMAILS);
        $excludedEmails = is_string($excludedEmails) ? $excludedEmails : '';
        $password = $input->getOption(self::OPTION_PASSWORD);
        $password = is_string($password) ? $password : '';
        $mailMask = $input->getOption(self::OPTION_EMAIL_MASK);
        $mailMask = is_string($mailMask) ? $mailMask : '';

        // option validation
        if ($password === '') {
            throw new LocalizedException(__('--password is a required option'));
        }

        if (strpos($mailMask, SqlHelper::ID_PLACEHOLDER) === false) {
            throw new LocalizedException(__('--email-mask must contain %1', SqlHelper::ID_PLACEHOLDER));
        }

        $resource = $this->getResource();
        $connection = $resource->getConnection();
        $encryptor = $this->getEncryptor();
        $passwordHash = $encryptor->getHash($password, true);

        $sql = $this->sqlHelper->buildSql($mailMask, $passwordHash);
        $sql = $this->sqlHelper->appendSqlWhereClause($sql, $excludedEmails);

        $result = $connection->query($sql);

        $output->writeln(sprintf('>>> %d users updated', $result->rowCount()));

        $output->writeln('Updating customer grid...');
        $this->updateCustomerGrid();
        $output->writeln('Updating customer grid done.');

        return 0;
    }

    /**
     * Refreshes the customer_grid
     */
    private function updateCustomerGrid(): void
    {
        $indexer = $this->getIndexer();
        $indexer->load(Customer::CUSTOMER_GRID_INDEXER_ID);
        $indexer->reindexAll();
    }

    /**
     * Helper method to return the database connection.
     *
     * @return ResourceConnection
     */
    protected function getResource(): ResourceConnection
    {
        /** @var ResourceConnection */
        return ObjectManager::getInstance()->get(ResourceConnection::class);
    }

    /**
     * Helper method to return the encryptor.
     *
     * @return EncryptorInterface
     */
    protected function getEncryptor(): EncryptorInterface
    {
        /** @var EncryptorInterface */
        return ObjectManager::getInstance()->get(EncryptorInterface::class);
    }

    /**
     * Helper method to return the application State.
     *
     * @return State
     */
    protected function getState(): State
    {
        /** @var State */
        return ObjectManager::getInstance()->get(State::class);
    }

    /**
     * Helper method to return the Indexer.
     *
     * @return Indexer
     */
    protected function getIndexer(): Indexer
    {
        /** @var Indexer */
        return ObjectManager::getInstance()->get(Indexer::class);
    }
}
