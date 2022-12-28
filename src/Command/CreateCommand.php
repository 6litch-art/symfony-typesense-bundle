<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Command;

use Symfony\UX\Typesense\Manager\CollectionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name:'typesense:create', aliases:[], description:'Create Typesenses indexes')]
class CreateCommand extends Command
{
    protected static $defaultName = 'typesense:create';
    private $collectionManager;

    public function __construct(CollectionManager $collectionManager)
    {
        parent::__construct();
        $this->collectionManager = $collectionManager;
    }

    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Create Typesenses indexes')

        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $defs = $this->collectionManager->getCollectionDefinitions();

        foreach ($defs as $name => $def) {
            $name = $def['name'];
            $typesenseName = $def['typesense_name'];
            try {
                $output->writeln(sprintf('<info>Deleting</info> <comment>%s</comment> (<comment>%s</comment> in Typesense)', $name, $typesenseName));
                $this->collectionManager->deleteCollection($name);
            } catch (\Typesense\Exceptions\ObjectNotFound $exception) {
                $output->writeln(sprintf('Collection <comment>%s</comment> <info>does not exists</info> ', $typesenseName));
            }

            $output->writeln(sprintf('<info>Creating</info> <comment>%s</comment> (<comment>%s</comment> in Typesense)', $name, $typesenseName));
            $this->collectionManager->createCollection($name);
        }

        return 0;
    }
}