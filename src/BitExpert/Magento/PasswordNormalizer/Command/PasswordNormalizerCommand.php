<?php

namespace BitExpert\Magento\PasswordNormalizer\Command;

use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PasswordNormalizerCommand
 * @package BitExpert\Magento\PasswordNormalizer\Command
 */
class PasswordNormalizerCommand extends AbstractMagentoCommand
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
                'Exclude email-addresses from being update by appending "WHERE email NOT LIKE ..." (example: --exclude-emails %@bitexpert.%)'
            )
            ->addOption(
                self::OPTION_EMAIL_MASK,
                'm',
                InputOption::VALUE_OPTIONAL,
                'Define the email-mask that is used to normalize the addresses. Must contain ' . self::ID_PLACEHOLDER . '. Default: customer_' . self::ID_PLACEHOLDER . '@example.com',
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
        /** @var \Magento\Framework\App\ResourceConnection $resource */
        $resource = ObjectManager::getInstance()->get(\Magento\Framework\App\ResourceConnection::class);
        /** @var \Magento\Framework\Encryption\EncryptorInterface $encryptor */
        $encryptor = ObjectManager::getInstance()->get(\Magento\Framework\Encryption\EncryptorInterface::class);

        // options
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

        $connection = $resource->getConnection();
        $passwordHash = $encryptor->getHash($password, true);

        // convert the email-mask input to SQL
        $mailMask = str_replace(
            self::ID_PLACEHOLDER,
            "',entity_id,'",
            $mailMask
        );

        // construct manual DB query, because magento2 is stupid and doesn't have good iterator or bulk-actions
        $sql = sprintf("UPDATE customer_entity SET email = CONCAT('%s'), password_hash = '%s'",
            $mailMask,
            $passwordHash
        );

        if (isset($excludedEmails))
        {
            $sql = sprintf(
                "%s WHERE email NOT LIKE '%s'",
                $sql,
                $excludedEmails
            );
        }

        $result = $connection->query($sql);

        $output->writeln(sprintf('>>> %d users updated',$result->rowCount()));

    }
}