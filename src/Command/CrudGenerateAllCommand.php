<?php

namespace Cadoteu\EntityToFormBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'crud:generate',
    description: 'génère tout de l\'entité ',
)]
class CrudGenerateAllCommand extends Command
{
    protected $entity;

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::OPTIONAL, 'nom de l\entité')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Pour passer les erreurs et continuer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /* --------------------------------- library -------------------------------- */
        $helper = $this->getHelper('question');
        /* --------------------------------- entity --------------------------------- */
        $this->entity = $input->getArgument('entity');
        if (!$this->entity) {
            $question = new Question('Entrer le nom de l\'entité:');
            $this->entity = $helper->ask($input, $output, $question);
        }
        /* -------------------------------- constant -------------------------------- */
        $force = new ArrayInput([
            'entity' => $this->entity,
            '--force' => $input->getOption('force'),
        ]);
        //secure $this->entity in minus
        $this->entity = strTolower($this->entity);
        //TODO: ajouter controle sur nocrud
        $init = $this->getApplication()->find('crud:init');
        $init->run($force, $output);

        $type = $this->getApplication()->find('crud:generate:type');
        $type->run($force, $output);

        $new = $this->getApplication()->find('crud:generate:new');
        $new->run($force, $output);

        $index = $this->getApplication()->find('crud:generate:index');
        $index->run($force, $output);

        $controller = $this->getApplication()->find('crud:generate:controller');
        $controller->run($force, $output);

        $io->success('Tous les fichiers ont été générés');
        return Command::SUCCESS;
    }
}
