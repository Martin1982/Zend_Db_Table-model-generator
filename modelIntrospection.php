<?php
/**
 * Zend Framwork Model Generator 
 * for MySQL tables
 */
Class Model_Introspection
{

    public function main($config)
    {
        $this->connectToDb($config['hostname'], $config['user'], $config['password'], $config['database']);
        $tables = $this->getTables();
        $this->createModelDirectory();
        foreach ($tables as $table) {
            $this->createModel($table);
        }
    }

    public function connectToDb($host, $user, $pass, $db)
    {
        mysql_connect($host, $user, $pass) or die("Could not connect to DB\n");
        mysql_select_db($db) or die("Could not use DB\n");
    }

    public function getTables()
    {
        $tablesQuery = "SHOW TABLES";
        $tableExec = mysql_query($tablesQuery) or die(mysql_error());
        while ($tableRes = mysql_fetch_array($tableExec, MYSQL_NUM)) {
            $tables[] = $tableRes[0];
        }

        return $tables;
    }

    public function createModelDirectory()
    {
        if (!is_dir('models')) {
            mkdir('models');
        }
    }

    public function createModel($table)
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
                    $refs.= "\t\t\t'refTableClass' => 'Application_Model_{$params[3][0]}',\n";
                    $refs.= "\t\t\t'refColumns' => '{$params[4][0]}',\n";
                    $refs.= "\t\t),\n";
                }
            }
            $refs.= "\t);\n";
        }
        $data = "class Application_Model_$tableName extends Zend_Db_Table {\n";
	$data = "\t".'$_name' . ' = \'' . $table  . '\';' . "\n";
        $data.= $refs;
        $data.="}";
        file_put_contents($filename, $data);
    }
}

class Cli
{
    public static function catchUserInput($default = null){
        $input = str_replace(PHP_EOL, '', fgets(STDIN));
        if ($default != null && empty($input)) {
            return $default;
        }
        return $input;
    }
}

echo "====================================================================================\n";
echo "Zend Framework Model Creater V0.01 by Martin de Keijzer <martin.dekeijzer@gmail.com>\n";
echo "PLEASE NOTE: currently this tool only supports MySQL\n";
echo "====================================================================================\n";
echo "Please enter the database host [localhost]: ";
$config['hostname'] = Cli::catchUserInput('localhost');
echo "Enter the database username [root]: ";
$config['user'] = Cli::catchUserInput('root');
echo "Enter the database password []: ";
$config['password'] =  Cli::catchUserInput('');
echo "Enter the database name: ";
$config['database'] =  Cli::catchUserInput('');

$introspector = new Model_Introspection();
$introspector->main($config);

echo "Models are now available in ./models\n";
