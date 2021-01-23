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
    const OPTION_PASSWORD = 'password';
    const OPTION_EXCLUDE_EMAILS = 'exclude-emails';
    const OPTION_EMAIL_MASK = 'email-mask';
    const OPTION_FORCE = 'force';
    const ID_PLACEHOLDER = '(ID)';

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
                'Define the email-mask that is used to normalize the addresses. Must contain ' . self::ID_PLACEHOLDER .
                '. Default: customer_' . self::ID_PLACEHOLDER . '@example.com',
                'customer_' . self::ID_PLACEHOLDER . '@example.com'
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
     * @return int|void
     * @throws LocalizedException
     * @throws \Zend_Db_Statement_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // check environment
        $force = $input->getOption(self::OPTION_FORCE);
        if (!($force || State::MODE_DEVELOPER == $this->getState()->getMode())) {
            throw new LocalizedException(__('This command can only be run in developer mode!'));
        }

        $excludedEmails = $input->getOption(self::OPTION_EXCLUDE_EMAILS);
        $excludedEmails = is_string($excludedEmails) ? $excludedEmails : '';
        $password = $input->getOption(self::OPTION_PASSWORD);
        $password = is_string($password) ? $password : '';
        $mailMask = $input->getOption(self::OPTION_EMAIL_MASK);
        $mailMask = is_string($mailMask) ? $mailMask : '';

        // option validation
        if (empty($password)) {
            throw new LocalizedException(__('--password is a required option'));
        }

        if (!strpos($mailMask, self::ID_PLACEHOLDER)) {
            throw new LocalizedException(__('--email-mask must contain %1', self::ID_PLACEHOLDER));
        }

        $resource = $this->getResource();
        $connection = $resource->getConnection();
        $encryptor = $this->getEncryptor();
        $passwordHash = $encryptor->getHash($password, true);

        $sql = $this->buildSql($mailMask, $passwordHash);
        $sql = $this->appendSqlWhereClause($sql, $excludedEmails);

        $result = $connection->query($sql);

        $output->writeln(sprintf('>>> %d users updated', $result->rowCount()));

        $output->writeln('Updating customer grid...');
        $this->updateCustomerGrid();
        $output->writeln('Updating customer grid done.');
    }

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

    /**
     * Refreshes the customer_grid
     */
    public function updateCustomerGrid(): void
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
        return ObjectManager::getInstance()->get(ResourceConnection::class);
    }

    /**
     * Helper method to return the encryptor.
     *
     * @return EncryptorInterface
     */
    protected function getEncryptor(): EncryptorInterface
    {
        return ObjectManager::getInstance()->get(EncryptorInterface::class);
    }

    /**
     * Helper method to return the application State.
     *
     * @return State
     */
    protected function getState(): State
    {
        return ObjectManager::getInstance()->get(State::class);
    }

    /**
     * Helper method to return the Indexer.
     *
     * @return Indexer
     */
    protected function getIndexer(): Indexer
    {
        return ObjectManager::getInstance()->get(Indexer::class);
    }
}
