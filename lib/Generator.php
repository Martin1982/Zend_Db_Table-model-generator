<?php

require_once 'lib/Cli.php';
Class Generator
{

    protected $_config;

    public function run()
    {
        $this->_renderIntro();
        $this->_config = $this->_requestConfig();
        $this->_connectToDb();
        $tables = $this->_getTables();
        $this->_createModelDirectory();
        foreach ($tables as $table) {
            $this->_createModel($table, $this->_config['modelPrefix']);
        }
        $this->_renderOutro();
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
        $config['database']     = Cli::catchUserInput("Enter the database name: ", '');
        $config['modelPrefix']  = Cli::catchUserInput("Enter the model prefix [Application_Model_]: ", 'Application_Model_');

        return $config;
    }

    protected function _renderOutro()
    {
        Cli::renderLine("Models are now available in ./models");
    }

    protected function _connectToDb()
    {
        mysql_connect($this->_config['hostname'], $this->_config['user'], $this->_config['password']) or die("Could not connect to DB\n");
        mysql_select_db($this->_config['database']) or die("Could not use DB: \n" . $this->_config['database'] . "\n");
    }

    protected function _getTables()
    {
        $tablesQuery = "SHOW TABLES";
        $tableExec = mysql_query($tablesQuery) or die(mysql_error());
        while ($tableRes = mysql_fetch_array($tableExec, MYSQL_NUM)) {
            $tables[] = $tableRes[0];
        }

        return $tables;
    }

    protected function _createModelDirectory()
    {
        if (!is_dir('models')) {
            mkdir('models');
        }
    }

    protected function _createModel($table, $modelPrefix)
    {
        $tableName = ucfirst($table);
        $filename = 'models/'.$tableName . '.php';

        if (file_exists($filename)) {
            return false;
        }

        $tablePropQuery = "SHOW CREATE TABLE " . $table;
        $tablePropExec = mysql_query($tablePropQuery);
        $tablePropRes = mysql_fetch_array($tablePropExec, MYSQL_NUM);

        $pattern = "@CONSTRAINT (.*)@";
        $subject = $tablePropRes[1];
        $numMatches = preg_match_all($pattern, $subject, $constraints);

        $refs = '';
        if($numMatches){
            $refs.= "\t" . 'protected $_referenceMap = array(' . "\n";
            $constraintsArray = $constraints[0];
            foreach ($constraintsArray as $constraint) {
                $pattern = "@CONSTRAINT `(.*)` FOREIGN KEY \(`(.*)`\) REFERENCES `(.*)` \(`(.*)`\)@";
                $numMatches = preg_match_all($pattern, $constraint, $params);
                if ($numMatches) {
                    $refs.= "\t\t'{$params[1][0]}'" . ' => array(' . "\n";
                    $refs.= "\t\t\t'columns' => '{$params[2][0]}',\n";
                    $refs.= "\t\t\t'refTableClass' => '" . $modelPrefix . ucfirst($params[3][0]) . "',\n";
                    $refs.= "\t\t\t'refColumns' => '{$params[4][0]}',\n";
                    $refs.= "\t\t),\n";
                }
            }
            $refs.= "\t);\n";
        }
        $data = "class " . $modelPrefix . $tableName . " extends Zend_Db_Table {\n";
	$data.= "\t".'$_name' . ' = \'' . $table  . '\';' . "\n";
        $data.= $refs;
        $data.="}";
        file_put_contents($filename, $data);
    }
}
