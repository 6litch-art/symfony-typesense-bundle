<?php

declare(strict_types=1);

namespace Typesense\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Exceptions\ObjectNotFound;

/**
 *
 */
#[AsCommand(name: 'typesense:action', aliases: [], description: 'Import collections from Database')]
class ActionCommand extends Command
{
    private TypesenseManager $typesenseManager;
    private const ACTIONS = [
        'create',
        'upsert',
        'update',
        'delete',
    ];
    private bool $isError = false;

    public function __construct(TypesenseManager $typesenseManager)
    {
        parent::__construct();
        $this->typesenseManager = $typesenseManager;
    }

    protected function configure()
    {
        $this->addArgument('action', null, 'Action modes for typesense import ("create", "upsert" or "update")', 'upsert');
        $this->addOption('collection', null, InputOption::VALUE_OPTIONAL, 'Collection name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $action = $input->getArgument('action');
        if (!in_array($action, self::ACTIONS, true)) {
            $io->error('Action option only takes the values : "' . implode('","', $action) . '\"');

            return 1;
        }

        $execStart = microtime(true);
        $populated = 0;

        $io->newLine();

        foreach ($this->typesenseManager->getCollections() as $name => $collection) {
            $metadata = $collection->metadata();
            $metadata->getObjectManager()->getConnection()->getConfiguration()->setSQLLogger(null);
            $class = $metadata->getClass();

            $output->writeln(sprintf('<info>Populating</info> <comment>%s</comment>', $name));

            $q = $metadata->getObjectManager()->createQuery('select e from ' . $class . ' e');
            $entities = $q->toIterable();

            $nbEntities = (int)$metadata->getObjectManager()->createQuery('select COUNT(u.id) from ' . $class . ' u')->getSingleScalarResult();
            $populated += $nbEntities;

            $data = [];
            foreach ($entities as $entity) {
                $entityData = $metadata->getTransformer()->convert($entity);
                $entityData['id'] = (string)$entity->getId();

                $data[] = $entityData;
            }

            $output->writeln("\t" . 'Importing <info>[' . $name . '] ' . $class . '</info> ');
            try {
                $response = $collection->documents()->import($data, $action);
            } catch (ObjectNotFound $exception) {
                $this->isError = true;
                $output->writeln("\t" . sprintf('Collection <comment>%s</comment> <info>does not exists</info> ', $name));
                continue;
            }

            if ($this->printErrors($io, $response ?? [])) {
                $this->isError = true;
                $io->error('Error happened during the import of the collection : ' . $name);

                return 2;
            }

            $io->text("\t" . 'DONE.');
        }

        $io->newLine();
        if (!$this->isError) {
            $io->success(sprintf(
                '%s element%s populated in %s seconds',
                $populated,
                $populated > 1 ? 's' : '',
                round(microtime(true) - $execStart, PHP_ROUND_HALF_DOWN)
            ));
        }

        return 0;
    }

    private function printErrors(SymfonyStyle $io, array $response): bool
    {
        $isError = false;
        foreach ($response as $item) {
            if (!$item['success']) {
                $isError = true;
                $io->error($item['error']);
            }
        }

        return $isError;
    }
}
