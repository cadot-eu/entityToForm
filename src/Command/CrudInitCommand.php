<?php

namespace Cadoteu\EntityToFormBundle\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Cadoteu\ParserDocblockBundle\ParserDocblock;
use Symfony\Component\Config\Definition\Exception\Exception;

#[AsCommand(
    name: 'crud:init',
    description: 'Initialise une entity',
)]
class CrudInitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::OPTIONAL, 'nom de l\'entity')
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

        //secure $entity in minus
        $entity = strTolower($entity);
        $class = 'App\Entity\\' . ucfirst($entity);
        $r = new \ReflectionClass(new $class()); //property of class
        /* --------------------------------- création de champs --------------------------------- */
        foreach ($r->getProperties() as $property) {
            $name = $property->getName();
            //récupération des options
            $prop = new ParserDocblock($property);
            $options[$name] = $prop->getOptions($property);
        }
        $sdir = '';
        //création des répertoires
        @mkdir("src/Form/$sdir/", 0777, true);
        @mkdir("src/Controller/$sdir/", 0777, true);
        @mkdir("templates/$sdir/$entity", 0777, true);
        //control des paramètres et ajout si nécessaires
        $trait = [
            "use App\Entity\TimeTrait;" => "use",
            "use Gedmo\Mapping\Annotation as Gedmo;" => "use",
            "use Symfony\Component\Validator\Constraints as Assert;" => "use",
            "#[ORM\HasLifecycleCallbacks()]" => "#[ORM",
            "use TimeTrait;" => "{",
        ];
        $fentity = 'src/Entity/' . ucfirst($entity) . '.php';
        foreach ($trait as $test => $comment) {
            $Sentity = file_get_contents($fentity);
            if (strpos($Sentity, $test) === false) {
                $insd = strpos($Sentity, $comment);
                $insf = strpos($Sentity, "\n", $insd);
                file_put_contents($fentity, substr($Sentity, 0, $insf) . "\n" . $test . substr($Sentity, $insf));
                $io->info("Paramètre `$test` ajouter dans la partie $comment ");
            }
        }
        $io->success('All necessary parameters are presents');

        return Command::SUCCESS;
    }
    static function ArrayToKeyValue(array $array)
    {
        return str_replace(['¤\'', '\'¤'], '', var_export($array, true));
    }
    /**
     * It takes a filename and an array of blocks, and updates the file with the blocks
     *
     * @param string filename The name of the file to be updated.
     * @param array blocks an array of blocks of code
     */
    public static function updateFile(string $filename, array $blocks, $force = false)
    {
        $input = new ArgvInput();
        $output = new ConsoleOutput();
        $io = new SymfonyStyle($input, $output);
        //open old json file if exist and cut by block
        if (file_exists("savecrud/" . $filename . ".json") && file_exists($filename) && $force == false) {
            //get old blocks
            $exblocks = json_decode(file_get_contents("savecrud/" . $filename . ".json"));
            //loop on blocks in new file
            $html = '';
            //by extension cut blocks of code personal
            switch (pathinfo($filename, PATHINFO_EXTENSION)) {
                case 'php':
                    preg_match_all('#//Here for add your Code(.*?)//end of your code#s', file_get_contents($filename), $match);
                    break;
                case 'twig':
                    preg_match_all('/{# Here for add your Code #}(.*?) #}/s', file_get_contents($filename), $match);
                    break;
            }
            //
            if (pathinfo($filename, PATHINFO_EXTENSION) == 'php') {
                $decalage = 1;
            } else {
                $decalage = 0;
            }
            /* -------------------- vérification du nombre de blocks -------------------- */
            if (!((count($exblocks) == count($blocks)) && (count($blocks) == (count($match[0]) + $decalage)))) {
                $io->error("Les blocks ne correspondent pas entre le fichier $filename et " . "savecrud/" . $filename . ".json");

                $io->info("Arrêt du script, vous pouvez supprimer la sauvegarde ou vérifier que vous n'avez pas supprimer de blocks");
                exit();
            }
            /* --------- vérification si un code est écris en dehors des blocks --------- */
            foreach ($exblocks as $num => $block) {
                $diff = CrudInitCommand::clean($block);
                if ($diff != '') {
                    $pos = strpos(CrudInitCommand::clean(file_get_contents($filename)), CrudInitCommand::clean($block));
                    if ($pos === false) {
                        $io->error("Dans le fichier:$filename.Ce block a été modifié:" . $block);
                        $io->info("Arrêt du script.Merci de vérifier que vous n'avez pas écris en dehors des blocks");
                        exit();
                    }
                }
                //maj du block
                if (pathinfo($filename, PATHINFO_EXTENSION) == 'php') {
                    if ($num != "0") {
                        $html .= $match[0][$num - 1];
                    }
                } else {
                    $html .= $match[0][$num];
                }
                $html .= $blocks[$num];
            }
            //$html = str_replace($exblocks, $blocks, file_get_contents($filename));
        } else {
            $html = "";
            foreach ($blocks as $num => $block) {
                switch (pathinfo($filename, PATHINFO_EXTENSION)) {
                    case 'php':
                        if ($num != 0) {
                            $html .= "//Here for add your Code //end of your code\n";
                        }
                        break;
                    case 'twig':
                        $html .= "{# Here for add your Code #} {# End of your Code #}\n";
                        break;
                }
                $html .= $block;
            }
        }
        // save new file
        @mkdir('savecrud/' . pathinfo($filename)['dirname'], 0777, true);
        file_put_contents("savecrud/" . $filename . ".json", json_encode($blocks));
        $retour = file_put_contents($filename, $html);
        if ($retour === false) throw new Exception("Erreru sur la création du fichier:" . $filename, 1);
        if (file_exists($filename)) {
            $io->info('File ' . $filename . ' généré ');
        }
    }
    /**
     * Method twigParser
     *
     * @param $html string twig with ¤...¤ for replacement
     * @param $tab array tableau des clefs à rechercher entre {{}} et à remplacer par value
     */
    public static function twigParser($html, $tab): string
    {
        foreach ($tab as $key => $value) {
            $html = str_replace('//¤' . $key . '¤', $value, $html); // that in first
            $html = str_replace('¤' . $key . '¤', $value, $html);
        }
        return $html;
    }


    /**
     * Given an array, find the key of an element, and another key to move before the first key.
     *
     * Return the array with the second key moved before the first key
     *
     * @param arr The array to be manipulated.
     * @param find The key of the element to be moved.
     * @param move The key of the element to be moved.
     *
     * @return The array with the element moved.
     */
    public static function moveKeyBefore($arr, $find, $move)
    {
        if (!isset($arr[$find], $arr[$move])) {
            return $arr;
        }

        $elem = [
            $move => $arr[$move],
        ];  // cache the element to be moved
        $start = array_splice($arr, 0, array_search($find, array_keys($arr)));
        unset($start[$move]);  // only important if $move is in $start
        return $start + $elem + $arr;
    }



    /**
     * clean block without spaces... by trim
     *
     * @param  mixed $string
     */
    public static function clean($string): string
    {
        return str_replace(["\t", "\n", "\r", "\0", "\x0B", "\n", "\r", " "], '', $string);
    }
}
