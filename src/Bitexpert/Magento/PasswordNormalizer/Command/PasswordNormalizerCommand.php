<?php

namespace Bitexpert\Magento\PasswordNormalizer\Command;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PasswordNormalizerCommand extends AbstractMagentoCommand
{


    protected function configure()
    {
        $this
            ->setName('dev:customer:normalize-passwords')
            ->setDescription('Normalizes all customer-emial-addresses and passwords');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Helllooooooo Woooooorld!');
    }
}