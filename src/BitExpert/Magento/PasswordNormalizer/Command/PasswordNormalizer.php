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

use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PasswordNormalizer extends AbstractMagentoCommand
{
    const OPTION_PASSWORD = 'password';
    const OPTION_EXCLUDE_EMAILS = 'exclude-emails';
    const OPTION_EMAIL_MASK = 'email-mask';
    const ID_PLACEHOLDER = '(ID)';

    protected function configure()
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
                'Exclude email-addresses from being update by appending "WHERE email NOT LIKE ..." '.
                '(example: --exclude-emails %@bitexpert.%)'
            )
            ->addOption(
                self::OPTION_EMAIL_MASK,
                'm',
                InputOption::VALUE_OPTIONAL,
                'Define the email-mask that is used to normalize the addresses. Must contain ' . self::ID_PLACEHOLDER .
                '. Default: customer_' . self::ID_PLACEHOLDER . '@example.com',
                'customer_(ID)@example.com'
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
        if (\Magento\Framework\App\State::MODE_DEVELOPER !== $this->getState()->getMode()) {
            throw new LocalizedException(__('This command can only be run in developer mode!'));
        }

        $excludedEmails = $input->getOption(self::OPTION_EXCLUDE_EMAILS);
        $password = $input->getOption(self::OPTION_PASSWORD);
        $mailMask = $input->getOption(self::OPTION_EMAIL_MASK);

        // option validation
        if (!isset($password)) {
            throw new LocalizedException(__('--password is a required option'));
        }

        if (!strpos($mailMask, self::ID_PLACEHOLDER)) {
            throw new LocalizedException(__('--email-mask must contain %1', self::ID_PLACEHOLDER));
        }


        $resource = $this->getResource();
        $connection = $resource->getConnection();
        $encryptor = $this->getEncryptor();
        $passwordHash = $encryptor->getHash($password, true);

        // convert the email-mask input to SQL
        $mailMask = str_replace(
            self::ID_PLACEHOLDER,
            "',entity_id,'",
            $mailMask
        );

        // construct manual DB query, because magento2 is stupid and doesn't have good iterator or bulk-actions
        $sql = sprintf(
            "UPDATE customer_entity SET email = CONCAT('%s'), password_hash = '%s'",
            $mailMask,
            $passwordHash
        );

        if (isset($excludedEmails)) {
            $sql = sprintf(
                "%s WHERE email NOT LIKE '%s'",
                $sql,
                $excludedEmails
            );
        }

        $result = $connection->query($sql);

        $output->writeln(sprintf('>>> %d users updated', $result->rowCount()));
    }

    /**
     * Helper method to return the database connection.
     *
     * @return \Magento\Framework\App\ResourceConnection
     */
    protected function getResource(): \Magento\Framework\App\ResourceConnection
    {
        return ObjectManager::getInstance()->get(\Magento\Framework\App\ResourceConnection::class);
    }

    /**
     * Helper method to return the encryptor.
     *
     * @return \Magento\Framework\Encryption\EncryptorInterface
     */
    protected function getEncryptor(): \Magento\Framework\Encryption\EncryptorInterface
    {
        return ObjectManager::getInstance()->get(\Magento\Framework\Encryption\EncryptorInterface::class);
    }

    /**
     * Helper method to return the application State.
     *
     * @return \Magento\Framework\Encryption\EncryptorInterface
     */
    protected function getState(): \Magento\Framework\App\State
    {
        return ObjectManager::getInstance()->get(\Magento\Framework\App\State::class);
    }
}
