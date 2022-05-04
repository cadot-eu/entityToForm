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
        $th = []; //contient les th pour l'entete du tableau
        $IDOptions = null; //contient les options d'ID
        /* ----------------------- on récupère tous les champs ---------------------- */
        $class = 'App\Entity\\' . $Entity;
        $r = new \ReflectionClass(new $class()); //property of class
        /* --------------------------------- entete --------------------------------- */
        $uses = []; //content uses
        //variable
        $adds = [];
        foreach ($r->getProperties() as $property) {
            $prop = new EntityToForm($property);
            $name = $prop->getName();
            $alias = $prop->getAlias($property);
            $type = $prop->getType($property);
            //on prend l'alias en priorité
            $select[$name] = $alias != '' ? $alias : $type;

            $properties[$name] = $property;
            //récupération des options
            $options[$name] = $prop->getOptions($property);
        }

        dd($field);
        $html = CrudHelper::twigParser(file_get_contents($fileController), [
            'fieldslug' => $fieldslug,
            'partie' => $this->attrs['PARTIE'],
            'entity' => $this->entity,
            'Entity' => ucFirst($this->entity),
            'extends' => $this->attrs['EXTEND'],
            'sdir' => $this->attrs['SDIR'] ? '\\' . $this->attrs['SDIR'] : '',
            'ssdir' => $this->attrs['SDIR'] ? $this->attrs['SDIR'] . '\\' : '',
            'ordre' => isset($options['id']['ORDRE']) ? $options['id']['ORDRE'] : null,
        ]);
        /** @var string $html */
        $blocks = (explode('//BLOCK', $html));
        //open model controller
        $fileController = __DIR__ . '/tpl/controller.incphp';
        if (!file_Exists($fileController)) {
            throw new Exception("Le fichier " . $fileController . ' est introuvable', 1);;
        }
        //create file
        CrudHelper::updateFile("src/Controller/" . $this->attrs['SDIR'] . "/" . ucfirst($this->entity) . 'Controller.php', $controller, $input->getOption('force'));
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
