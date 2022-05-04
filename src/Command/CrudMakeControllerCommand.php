<?php

namespace Cadoteu\EntityToFormBundle\Command;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Cadoteu\ParserDocblockBundle\ParserDocblock;

#[AsCommand(
    name: 'crud:generate:controller',
    description: 'Génère un controller de l\'entité',
)]
class CrudMakeControllerCommand extends Command
{
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
        /* ----------------------- on récupère tous les champs ---------------------- */
        $class = 'App\Entity\\' . $Entity;
        $docs = new ParserDocblock($entity);
        $options = $docs->getOptions();
        $fieldslug = $docs->getArgumentOfAttributes('slug', "Gedmo\Mapping\Annotation\Slug", 'fields')[0];
        $fileController = __DIR__ . '/tpl/controller.incphp';
        $html = CrudInitCommand::twigParser(file_get_contents($fileController), [
            'partie' => "/admin//",
            'fieldslug' => $fieldslug,
            'entity' => $entity,
            'Entity' => $Entity,
            'extends' => '/admin/base.html.twig',
            'sdir' =>  '',
            'ssdir' => '',
            'ordre' => isset($options['id']['ORDRE']) ? $options['id']['ORDRE'] : null,
        ]);
        /** @var string $html */
        $blocks = (explode('//BLOCK', $html));
        //open model controller
        $fileController = __DIR__ . '/tpl/controller.incphp';
        if (!file_Exists($fileController)) {
            throw new Exception("Le fichier " . $fileController . ' est introuvable', 1);
        }
        //create file
        CrudInitCommand::updateFile("src/Controller/" . $Entity . 'Controller.php', $blocks, $input->getOption('force'));
        return Command::SUCCESS;
    }

    public function ReturnBlocksController(): array
    {


        //pour le slug
        $fieldslug = '';
        if (isset($this->attrs['slug'])) {
            foreach ($this->attrs['slug']['AUTRE'] as $item) {
                if (strpos($item, '@Gedmo\Slug') !== false) {
                    $deb = strpos($item, 'fields={"');
                    $end = strpos($item, '"}', $deb);
                    $fieldslug = substr($item, $deb + strlen('fields={"'), $end - $deb - strlen('fields={"'));
                }
            }
        }

        return $blocks;
    }
}
