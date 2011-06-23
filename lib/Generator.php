<?php

require_once 'lib/Cli.php';

class Generator
{
    protected $_tabs = false;
    protected $_config;

    public function run()
    {
        $this->_renderIntro();
        $config = $this->_requestConfig();

        $this->_connectToDb($config);

        $tables = $this->_getTables();
        $models = $this->getModelInfo($tables);

        $this->_createModelDirectory($config);
        $this->_writeModels($models, $config);
        $this->_renderOutro($config);
    }

    protected function _renderIntro()
    {
        Cli::renderLine("====================================================================================");
        Cli::renderLine("Zend Framework Model Creater V0.01 by Martin de Keijzer <martin.dekeijzer@gmail.com>");
        Cli::renderLine("PLEASE NOTE: currently this tool only supports MySQL");
        Cli::renderLine("====================================================================================");
    }

    protected function _requestConfig(){
        $config['hostname']     = Cli::catchUserInput("Please enter the database host [localhost]: ", 'localhost');
        $config['user']         = Cli::catchUserInput("Enter the database username [root]: ", 'root');
        $config['password']     = Cli::catchUserInput("Enter the database password []: ", '');
        $config['database']     = Cli::catchUserInput("Enter the database name []: ", '');
        $config['modelPrefix']  = Cli::catchUserInput("Enter the model prefix [Application_Model_]: ", 'Application_Model_');
        $config['modelPath']    = Cli::catchUserInput("Enter a path to place your models [./models]", 'models');
        $config['tablePrefix']  = Cli::catchUserInput("Enter the table prefix to remove in modelnames []: ", '');

        return $config;
    }

    protected function _renderOutro($config)
    {
        Cli::renderLine("Models are now available in ./".$this->getModelsDirectory($config));
    }

    protected function _connectToDb($config)
    {
        mysqli_connect($config['hostname'], $config['user'], $config['password']) or die("Could not connect to DB\n");
        mysqli_select_db($config['database']) or die("Could not use DB: \n" . $config['database'] . "\n");
    }

    protected function _getTables()
    {
        $tables = array();
        $tablesQuery = "SHOW TABLES";
        $tableExec = mysqli_query($tablesQuery) or die(mysqli_error());
        while ($tableRes = mysqli_fetch_array($tableExec, MYSQLI_NUM)) {
            $tables[] = $tableRes[0];
        }

        return $tables;
    }

    protected function getModelsDirectory($config)
    {
        return $config['modelPath'];
    }

    protected function _createModelDirectory($config)
    {
        $modelsDir = $this->getModelsDirectory($config);
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir);
        }
    }

    protected function getModelInfo($tables)
    {
        $models = array();

        foreach ($tables as $table) {
            $tablePropQuery = "SHOW CREATE TABLE " . $table;
            $tablePropExec = mysqli_query($tablePropQuery);
            $tablePropRes = mysqli_fetch_array($tablePropExec, MYSQLI_NUM);

            $pattern = "@CONSTRAINT (.*)@";
            $subject = $tablePropRes[1];
            $numMatches = preg_match_all($pattern, $subject, $constraints);

            if($numMatches){

                $constraintsArray = $constraints[0];
                foreach ($constraintsArray as $constraint) {
                    $pattern = "@CONSTRAINT `(.*)` FOREIGN KEY \(`(.*)`\) REFERENCES `(.*)` \(`(.*)`\)@";
                    $numMatches = preg_match_all($pattern, $constraint, $params);
                    if ($numMatches) {
                        $ref = array();
                        $ref['pk'] = $params[1][0];
                        $ref['columns'] = $params[2][0];
                        $ref['refTableClass'] = $this->getModelName($params[3][0], true);
                        $ref['refColumns'] = $params[4][0];
                        $models[$table]["refs"][] = $ref;
                        $models[$params[3][0]]["deps"][] = $this->getModelName($table, true);
                    }
                }
            }
        }
        return $models;
    }

    public function getModelName($str, $capitalise_first_char = false)
    {
        if($this->_config['tablePrefix'] != '') {
            $str = str_replace($this->_config['tablePrefix'], '', $str);
        }
        if($capitalise_first_char) {
          $str[0] = strtoupper($str[0]);
        }
        $func = create_function('$c', 'return strtoupper($c[1]);');
        return preg_replace_callback('/_([a-z])/', $func, $str);

    }

    protected function _writeModels($models, $config)
    {
        $modelPrefix = $config['modelPrefix'];
        foreach($models as $table => $model) {
            $tableName = $this->getModelName($table, true);
            $filename = $this->getModelsDirectory($config) . '/'.$tableName . '.php';

            if (file_exists($filename)) {
                continue;
            }
            $data = "<?php\n";
            $data.= "class " . $modelPrefix . $tableName . " extends Zend_Db_Table_Abstract\n{\n";
            $data.= $this->tab().'protected $_name' . ' = \'' . $table  . '\';' . "\n";
            if(isset($model['deps']) && is_array($model['deps'])) {
                $data.= $this->tab().'protected $_dependentTables = array(' . "\n";
                $depsDone = array();
                foreach($model['deps'] as $dep) {
                    if(!in_array($dep, $depsDone)) {
                        $data .= $this->tab(2) . "'$dep',\n";
                        $depsDone[] = $dep;
                    }
                }
                $data .= $this->tab() . ");\n\n";
            }
            if(isset($model['refs']) && is_array($model['refs'])) {
                $data.= $this->tab() . 'protected $_referenceMap = array(' . "\n";
                foreach($model['refs'] as $ref) {
                    $data.= $this->tab(2)."'{$ref['pk']}'" . ' => array(' . "\n";
                    $data.= $this->tab(3)."'columns' => '{$ref['columns']}',\n";
                    $data.= $this->tab(3)."'refTableClass' => '" . $modelPrefix . ucfirst($ref['refTableClass']) . "',\n";
                    $data.= $this->tab(3)."'refColumns' => '{$ref['refColumns']}',\n";
                    $data.= $this->tab(2)."),\n";
                }
                $data.= $this->tab().");\n\n";

            }
            $data.="}";
            file_put_contents($filename, $data);

        }
    }

    protected function tab($amount = 1)
    {
        if(!is_numeric($amount) || $amount < 0) {
            $amount = 0;
        }

        if ($this->_tabs) {
            $tab = '\t';
        } else {
            $tab = '    ';
        }

        $ret = '';
        for($i=0;$i<$amount;$i++) {
            $ret .= $tab;
        }
        return $ret;
    }
}
