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
    name: 'crud:generate:index',
    description: 'Génère le fichier index de l\'entité',
)]
class CrudMakeIndexCommand extends Command
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
        foreach ($r->getProperties() as $property) {
            $name = $property->getName();
            //récupération des options
            $prop = new EntityToForm($property);
            $options = $prop->getOptions($property);
            //creation des th
            if (!isset($options['tpl']['no_index']) && $name != 'deletedAt' && $name != 'createdAt' && $name != 'updatedAt') {
                $th[] = '<th><a class="btn btn-outline-primary {{ app.request.query.get("tri") == "' . $name . '" ? \'active\' }} " href=\'?tri=' . $name . '&&ordre={{ app.request.query.get("ordre")=="DESC" ? "ASC":"DESC" }}\'>' . $name . '</a></th>';
            }
        }
        //gestion du timetrait
        $th[] = <<<'EOT'
        {%if action=="deleted" %}
            <th>
            <a class="btn btn-outline-primary {{ app.request.query.get(" tri") == 'deletedAt' ? 'active' }} " href='?tri=deletedAt&&ordre={{ app.request.query.get("ordre")=="DESC" ? "ASC":"DESC" }}'>effacé</a>
            </th>
        {% endif %}
        <th>
            <a class="btn btn-outline-primary {{ app.request.query.get(" tri") == 'createdAt' ? 'active' }} " href='?tri=createdAt&&ordre={{ app.request.query.get("ordre")=="DESC" ? "ASC":"DESC" }}'>créé</a>
        </th>
        <th>
            <a class="btn btn-outline-primary {{ app.request.query.get(" tri") == 'updatedAt' ? 'active' }} " href='?tri=updatedAt&&ordre={{ app.request.query.get("ordre")=="DESC" ? "ASC":"DESC" }}'>modifié</a>
        </th>
        EOT;
        /* ---------------------------------- body ---------------------------------- */
        $tableauChoice = '';
        foreach ($r->getProperties() as $property) {
            $class = []; //contient les class à insérer
            $prop = new EntityToForm($property);
            $alias = $prop->getAlias($property);
            $type = $prop->getType($property);
            //on prend l'alias en priorité
            $select = $alias != '' ? $alias : $type;
            //récupération des options
            $options = $prop->getOptions($property);
            $name = $prop->getName();
            /* ------------------------- creation des idoptions ------------------------- */
            if ($name == 'id') {
                $IDOptions = $options;
            }
            /* ----------------------------- ajout des class ---------------------------- */
            if (isset($options['class'])) {
                $class[] = implode(' ', array_keys($options['class']));
            }
            /* ---------------------------- gestion des twigs --------------------------- */
            $twig = isset($options['twig']) ? '|' . implode('|', array_keys($options['twig'])) : '|striptags|u.truncate(20, "...")';
            /* ----------------------------- création des td ---------------------------- */
            if (!isset($options['tpl']['no_index'])) {
                switch ($select) {
                    case 'generatedvalue': //id
                        /* Checking if the twig option is set, if it is, it will implode the array keys
                       of the twig option and add a pipe to the beginning of the string. */
                        $twig = isset($options['twig']) ? '|' . implode('|', array_keys($options['twig'])) : '';
                        $td[] = '<td class="my-auto ' . implode(' ', $class) . '" > {{' . "$Entity.$name$twig" . '}}' . "\n</td>";;
                        break;
                    case 'string':
                        $td[] = '<td class="my-auto ' . implode(' ', $class) . '" title="{{' . "$Entity.$name" . '}}"> {{' . "$Entity.$name$twig" . '}}' . "\n</td>";
                        break;
                    case 'integer':
                        $td[] = '<td class="my-auto ' . implode(' ', $class) . '" title="{{' . "$Entity.$name" . '}}"> {{' . "$Entity.$name$twig" . '}}' . "\n</td>";
                        break;
                    case 'text':
                        $td[] = '<td class="my-auto ' . implode(' ', $class) . '" title="{{' . "$Entity.$name" . '}}"> {{' . "$Entity.$name$twig" . '}}' . "\n</td>";
                        break;
                    case 'file':
                        $tdtemp = '<td class="my-auto ' . implode(' ', $class) . '" title="{{' . "$Entity.$name" . '}}"> ';
                        if (isset($options['tpl']) && isset($options['tpl']['index_FileImage'])) {
                            //retourne une miniature
                            $tdtemp .= "{% if $Entity.$name is not empty %}<span title=\"{{TBgetFilename($Entity.$name)$twig}}\" data-controller='bigpicture' bPsrc='{{TBgetPublic($Entity.$name)}}'><img src=\"{{asset($Entity.$name)|imagine_filter('icone')}}\" class=\"img-fluid\"></span> {% endif %}";
                        } elseif (isset($options['tpl']) && isset($options['tpl']['index_FileImageNom'])) {
                            //retourne une miniature
                            $tdtemp .= "{% if $Entity.$name is not empty %}<span data-controller='bigpicture' bPsrc='{{TBgetPublic($Entity.$name)}}'><img src=\"{{asset($Entity.$name)|imagine_filter('icone')}}\" class=\"img-fluid\">{{TBgetFilename($Entity.$name)$twig}}</span> {% endif %}";
                        } else {
                            //retoune que le nom du fichier
                            $tdtemp .= "{% if $Entity.$name is not empty %}<span data-controller='bigpicture' bPsrc='{{TBgetPublic($Entity.$name)}}'>{{TBgetFilename($Entity.$name)$twig}}</span> {% endif %}";
                        }
                        $td[] = $tdtemp . '' . "\n</td>";
                        break;
                    case 'choiceenplace':
                        $twig = isset($options['twig']) ? '|' . implode('|', array_keys($options['twig'])) : '';
                        $tableauChoice .= '{% set choice_' . $name . '=' . json_encode($options['options']) . ' %}' . "\n";
                        //création de la ligne
                        $td[] = "<td class=\"my-auto\">
                                {% set retour=0 %}
                                {% for test,value in choice_$name %}
                                    {% if test==$Entity.$name %}
                                        {% set retour=loop.index0 %}
                                    {% endif %}
                                {% endfor %}
                                {% if retour+1==choice_$name|length %}
                                    {% set numr=0 %}
                                {% else %}
                                    {% set numr=retour+1 %}
                                {% endif %}
                                <a href=\"{{path('" . $entity . "_etat',{'id':$Entity.id,'type':'" . $name . "','valeur':choice_" . $name . "|keys[numr]})}}\" style='font-size:2rem;'  title='{{ choice_" . $name . "|keys[retour]}}'> {{ choice_" . $name . "[ choice_" . $name . "|keys[retour]]$twig|raw}}</a>\n</td>";
                        break;
                    case 'color':
                        $td[] = '<td class="my-auto"><div class="boxcolor" style="background-color:{{' . $Entity . '.' . $name . '}}"></div>' . "\n</td>";
                        break;
                    case 'collection':
                        //field for show
                        $return = isset($options['label']) ? $options['label'] : 'id';
                        //for separate field
                        $separation = isset($options['separation']) ? $options['separation'] : ';';
                        $td[] = '<td class="my-auto">' . "{% for " . $name . "_item in " . $Entity . ".$name %}\n{{" . $name . "_item.$return$twig}}{{loop.last?'':'$separation'}}\n{% endfor %}" . "\n</td>";
                        break;
                    case 'entity':
                        //field for show
                        $return = isset($options['label']) ? array_keys($options['label'])[0] : 'id';
                        if ($type == 'manytomany'  || $type == 'onetomany') {
                            //for separate field
                            $separation = isset($options['separation']) ? $options['separation'] : ';';
                            $td[] = '<td class="my-auto">' . "{% for " . $name . "_item in " . $Entity . ".$name %}\n{{" . $name . "_item.$return$twig}}{{loop.last?'':'$separation'}}\n{% endfor %}" . "\n</td>";
                        } else {
                            $td[] = '<td class="my-auto">kkk' . '{{ ' . $Entity . '.' . $name . '.' . $return . ' is defined ? ' . $Entity . '.' . $name . '.' . $return . '}}' . "\n</td>";
                        }
                        break;
                    default:
                        dump('non géré dans makeindex:' . $select);
                        break;
                }
            }
        }
        /* -------------------------- protection par nocrud ------------------------- */
        if ((isset($IDOptions['nocrud']))) {
            $io->warning("This Entity is protected against crud");
        }
        /* --------------------------- gestion des actions -------------------------- */
        $actions = [
            'new' => 'icone bi bi-file-plus',
            'edit' => 'icone bi bi-pencil-square',
            'clone' => 'bi bi-file-earmark-plus',
        ];
        foreach ($actions as $action => $title) {
            if (!isset($IDOptions['tpl']['no_action_' . $action])) {
                $resaction[$action] =  "<a class='btn btn-xs btn-primary'  title='$action' href=\"{{ path('$entity" . "_$action" . "', {'id': $Entity.id }) }}\"><i class='icone $title'></i></a>";
            }
        }
        /* ----------------------------- timestamptable ----------------------------- */
        //timestamptable
        $timestamptable = ['createdAt', 'updatedAt', 'deletedAt'];
        foreach ($timestamptable as $time) {
            if ($name == 'deletedAt') {
                $td[] .= "{%if action==\"deleted\" %}<td>{{ $Entity.$time is not empty ? $Entity.$time|date('d/m à H:i', 'Europe/Paris')}}</td>{% endif %}";
            } else {
                $td[] .= "<td>{{ $Entity.$time is not empty ? $Entity.$time|date('d/m à H:i', 'Europe/Paris')}}</td>";
            }
        }
        /* --------------------------------- hide BY ID -------------------------------- */
        $ifhide = 'true ';
        if ((isset($IDOptions['hide']))) {
            foreach (array_keys($IDOptions['hide']) as $hide) {
                $ifhide .= "and $Entity." . explode('=>', $hide)[0] . "  != '" . explode('=>', $hide)[1] . "'";
            }
        }

        //open model controller
        $fileIndex = __DIR__ . '/tpl/index.html.twig';
        if (!file_Exists($fileIndex)) {
            throw new Exception("Le fichier " . $fileIndex . " est introuvable", 1);
        }
        $html = CrudHelper::twigParser(file_get_contents($fileIndex), [
            'hide' => $ifhide,
            'rows' => implode("\n", $td),
            'entete' => implode("\n", $th),
            'entity' => $entity,
            'Entity' => $Entity,
            'no_action_edit' => $resaction['edit'],
            'extends' => '/admin/base.html.twig',
            'no_action_add' => !isset($IDOptions['tpl']['no_action_add']) ? "true" : "false",
            'no_access_deleted' => !isset($IDOptions['tpl']['no_action_deleted']) ? "true" : "false",
            'tableauChoice' => $tableauChoice,
        ]);
        /** @var string $html */
        $blocks = (explode('{#BLOCK#}', $html));
        CrudHelper::updateFile("templates/" . $entity . '/index.html.twig', $blocks, $input->getOption('force'));
        return Command::SUCCESS;
    }
}
