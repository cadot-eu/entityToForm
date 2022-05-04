<?php

namespace Cadoteu\EntityToFormBundle\Command;

use Cadoteu\ParserDocblockBundle\ParserDocblock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Config\Definition\Exception\Exception;

#[AsCommand(
    name: 'crud:generate:new',
    description: 'Génère le fichier new de l\'entité',
)]
class CrudMakeNewCommand extends Command
{
    protected $attrs;
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
        $entity = $input->getArgument('entity');
        if (!$entity) {
            $question = new Question('Entrer le nom de l\'entité:');
            $entity = $helper->ask($input, $output, $question);
        }

        /* ------------------------- initialisation variable ------------------------ */
        $entity = strTolower($entity);
        $Entity = ucfirst($entity);
        $docs = new ParserDocblock($entity);
        /* --------------------------------- entete --------------------------------- */
        $uses = []; //content uses
        //variable
        $rows = [];
        foreach ($docs->getOptions() as $name => $options) {
            if (!isset($options['tpl']['no_form']) && $name != 'id') {
                switch ($select = $docs->getSelect($name)) {
                    case 'collection':
                        break;
                    case 'file':
                        $rows[] = '<div class="mb-3 row"> {{form_label(form.image)}}
                    <div class="col-sm-8">
                        {{form_widget(form.image)}}
                    </div>
                    <div class="col-sm-2 my-auto">
                    {% if ' . $entity . '.' . $name . ' %}
                        <img  data-controller="bigpicture" bpsrc="{{asset(' . $entity . '.' . $name . ')}}" src="{{asset(form.vars.value.image)|imagine_filter(\'icone\')}}">
                    {% endif %}
                    </div>
                </div>';
                        break;
                    default: {
                            $resattrs = ''; // count($attrs) > 1 ? ", { 'attr':{\n" . implode(",\n", $attrs) . "\n}\n}" : '';
                            $rows[] = '{{ form_row(form.' . $name . $resattrs . ') }}' . "\n";
                        }
                }
            }
        }
        //open model controller
        $fileNew = __DIR__ . '/tpl/new.html.twig';
        if (!file_Exists($fileNew)) {
            throw new Exception("Le fichier " . $fileNew . " est introuvable", 1);
        }
        $html = CrudInitCommand::twigParser(file_get_contents($fileNew), array(
            'form_rows' => implode("\n", $rows),
            'entity' => $entity,
            'Entity' => $Entity,
            'extends' => '/admin/base.html.twig',
            'sdir' => ''
        ));
        $blocks = (explode('//BLOCK', $html));
        CrudInitCommand::updateFile("templates/" . $entity . '/new.html.twig', $blocks, $input->getOption('force'));
        return Command::SUCCESS;
    }
}
