<?php

namespace Cadoteu\EntityToFormBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Cadoteu\EntityToFormBundle\EntityToForm;

#[AsCommand(
    name: 'crud:generate:type',
    description: 'Génère le fichier type de l\'entité',
)]
class CrudMakeTypeCommand extends Command
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
        $docs = new EntityToForm($entity);
        /* --------------------------------- entete --------------------------------- */
        $uses = []; //content uses
        //variable
        $adds = [];
        foreach ($docs->getOptions() as $name => $options) {
            $tempadds = '->add(\'' . $name . '\',null,';
            $opts = [];
            $attrs = [];
            if (!isset($options['tpl']['no_form']) && $name != 'id') {
                switch ($select = $docs->getSelect($name)) {
                    case 'simple':
                        $attrs['data-controller'] = 'ckeditor';
                        $attrs['data-ckeditor-toolbar-value'] = 'simple';
                        break;
                    case 'text':
                        $attrs['data-controller'] = 'ckeditor';
                        break;
                    case 'password':
                        $tempadds = "->add('$name',RepeatedType::class,";
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\RepeatedType;";
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\PasswordType;";
                        $opts['type'] = 'PasswordType::class';
                        $opts['mapped'] = false;
                        $opts['first_options'] = array('label' => 'Mot de passe');
                        $opts['second_options'] = array('label' => 'Répétez le');
                        $opts['invalid_message'] = 'Les mots de passe ne correspondent pas';
                        break;
                    case 'file':
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\FileType;";
                        $tempadds = "\n->add('$name',FileType::class,";
                        break;
                    case 'image':
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\FileType;";
                        $tempadds = "\n->add('$name',FileType::class,";
                        $attrs['accept'] = 'image/*';
                        $opts['mapped'] = false;
                        $opts['required'] = false;
                        break;
                    case 'hidden':
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\HiddenType;";
                        $tempadds = "\n->add('$name',HiddenType::class";
                        break;
                    case 'collection':
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\CollectionType;";

                        //field for show
                        $return = isset($options['options']['label']) ? $options['options']['label'] : 'id';
                        //get name of entity
                        foreach ($options['AUTRE'] as $num => $autre) {
                            $target = (explode('::class', explode('targetEntity=', $autre)[1])[0]); //error * empty ;-)
                            $uses[] = "use App\Form\site\\" . ucfirst($target) . "Type;";
                        }
                        $tempadds = "\n->add('$name',CollectionType::class,['entry_type' => " . ucfirst($target) . "Type::class,";
                        //for entry use XTRA for add option
                        if (isset($options['XTRA'])) {
                            foreach ($options['XTRA'] as $entry) {
                                $pos = strpos($entry, '=>');
                                $tempadds = "'" . substr($entry, 0, $pos) . "'" . substr($entry, $pos) . ',';
                            }
                        }
                        $tempadds = "'by_reference' => false";
                        $tempadds = ']';
                        break;
                    case 'choice':
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\ChoiceType;";
                        $tempadds = "->add('$name',ChoiceType::class,";
                        $opts['choices'] =  $options['options'];
                        break;
                    case 'choiceenplace':
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\ChoiceType;";
                        $tempadds = "\n->add('$name',ChoiceType::class,";
                        $opts['choices'] =  $options['options'];
                        break;
                    case 'color':
                        $uses[] = "use Symfony\Component\Form\Extension\Core\Type\ColorType;";
                        $tempadds = "\n->add('$name',ColorType::class,";
                        break;
                    case 'entity':
                        //get name of entity
                        $target = $docs->getArgumentOfAttributes($name, 0, 'targetEntity');
                        $EntityTarget = array_reverse(explode('\\', $target))[0];
                        $uses[] = "use Symfony\Bridge\Doctrine\Form\Type\EntityType;";
                        $uses[] = "use Doctrine\ORM\EntityRepository;";
                        $uses[] = "use $target;";
                        $tempadds = "\n->add('$name',EntityType::class,";
                        $opts['class'] = "¤$EntityTarget::class¤";
                        $opts['query_builder'] = "¤
                        function (EntityRepository \$er) {
                            return \$er->createQueryBuilder(\"u\")
                                ->orderBy(\"u.nom\", \"ASC\")
                                ->andwhere(\"u.deletedAt IS  NULL\");
                        }
                        ¤";
                        if ($docs->getAttributes($name)[0]->getName() == 'Doctrine\ORM\Mapping\OneToMany') {
                            $opts['multiple'] = true;
                        }
                        $opts['choice_label'] = array_keys($options['label'])[0];
                        break;
                    case 'generatedvalue': //id

                        break;
                    case 'datetime':
                        $opts['widget'] = 'single_text';
                        break;
                    case 'integer':


                        break;
                    default: {
                            dump('non géré dans maketype:' . $select . '[' . $name . ']');
                        }
                }
                //surcharge opt
                $finalOpts = isset($options['opt']) ? array_merge($options['opt'], $opts) : $opts;
                $finalAttrs = isset($options['attr']) ? array_merge($options['attr'], $attrs) : $attrs;
                //add attrs in opt
                if (isset($finalAttrs)) {
                    $finalOpts['attr'] = $finalAttrs;
                }
                $tempopts = isset($finalOpts) ? CrudInitCommand::ArrayToKeyValue($finalOpts) : "";
                // ($select == 'image') dd($tempopts);
                $adds[] = $tempadds . "\n" . $tempopts . ')';
            }
        }
        $fileType = dirname(__FILE__) . '/tpl/type.incphp';
        $html = CrudInitCommand::twigParser(
            file_get_contents($fileType),
            [
                'entity' => $entity,
                'Entity' => $Entity,
                'extends' => '/admin/base.html.twig',
                'sdir' => '',
                'adds' => ' $builder' . implode("\n", $adds),
                'uses' => implode("\n", array_unique($uses)),
            ]
        );
        /* ------------------------------ RETURN BLOCKS ----------------------------- */
        $blocks = (explode('//BLOCK', $html));
        CrudInitCommand::updateFile("src/Form/" . $Entity . 'Type.php', $blocks, $input->getOption('force'));
        return Command::SUCCESS;
    }
}
