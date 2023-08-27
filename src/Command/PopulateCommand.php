<?php

declare(strict_types=1);

namespace Typesense\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Exceptions\ObjectNotFound;

/**
 *
 */
#[AsCommand(name: 'typesense:populate', aliases: [], description: 'Import collections from Database')]
class PopulateCommand extends Command
{
    private TypesenseManager $typesenseManager;
    private bool $isError = false;

    public function __construct(TypesenseManager $typesenseManager)
    {
        parent::__construct();
        $this->typesenseManager = $typesenseManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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
                $data[] = $metadata->getTransformer()->convert($entity);
            }

            $output->writeln("\t" . 'Importing <info>[' . $name . '] ' . $class . '</info> ');
            $response = $collection->documents()->import($data, 'upsert');

            if ($this->printErrors($io, $response ?? [])) {
                $this->isError = true;
                $io->error('Something wrong happened during the import of the collection ' . $name);

                return 2;
            }

            $this->typesenseManager->getFinder($name)->cache()->clear();
            if($this->isError) $io->text("\t" . 'ERR.');
            else $io->text("\t" . 'DONE.');
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
