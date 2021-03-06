<?php
namespace App\Tasks;

use App\Lib\Database;
use gossi\codegen\generator\CodeGenerator;
use gossi\codegen\model\PhpClass;
use gossi\codegen\model\PhpMethod;
use gossi\codegen\model\PhpParameter;
use gossi\codegen\model\PhpProperty;
use Slim\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ModelGenerator
 * @package App\Tasks
 */
class ModelGenerator extends Command {
	/**
	 * @var Container
	 */
	protected $container;
	/**
	 * @var Database
	 */
	protected $db;

	/**
	 * ModelGenerator constructor.
	 *
	 * @param Container $container
	 *
	 * @throws \Interop\Container\Exception\ContainerException
	 */
	public function __construct(Container $container) {
		parent::__construct();
		$this->container = $container;
		$this->db = $container->get("db");
	}

	/**
	 *
	 */
	protected function configure() {
		$this
			->setName("generator:model")
			->setDescription("Generates a model from a database table")
			->addArgument("table", InputOption::VALUE_REQUIRED, "Table to generate model for")
			->addOption("subdir", "s", InputOption::VALUE_OPTIONAL, "Sub directory under Model/ to put the model file")
			->addOption("inserter", "i", InputOption::VALUE_OPTIONAL, "Generate an inserter function", false)
			->addOption("updaters", "u", InputOption::VALUE_OPTIONAL, "Generate updater functions", false)
			->addUsage("To generate a database model, first you must have the table generated and inserted into the database.\n  Then you can do: php bin/App generator:model <tableName> and it will generate and store the resulting database model.\n  -s puts it into a directory under Model/ and -i generates an inserter");
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int|null|void
	 * @throws \Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		if(!empty($input->getArgument("table"))) {
			$table = $input->getArgument("table");
			$sub = $input->getOption("subdir");

			$output->writeln("<info>Generating table model for: </info> {$table}");

			if(!empty($sub))
				$modelPath = __DIR__ . "/../Model/{$sub}/" . ucfirst($table) . ".php";
			else
				$modelPath = __DIR__ . "/../Model/" . ucfirst($table) . ".php";
			if(file_exists($modelPath))
				return $output->writeln("<error>Error, file already exists</error>");

			$tableColumns = $this->db->query("SHOW COLUMNS FROM {$table}");

			$class = new PhpClass();
			$qualifiedName = !empty($sub) ? "App\\Model\\" . ucfirst($sub) . "\\{$table}" : "App\\Model\\{$table}";
			$class
				->setQualifiedName($qualifiedName)
				->setProperty(
					PhpProperty::create("container")
					->setVisibility("protected")
					->setType("Container"))
				->setMethod(PhpMethod::create("__construct")
				->addParameter(PhpParameter::create("container")
				->setType("Container"))
				->setBody("\$this->container = \$container;\n\$this->db = \$container->get(\"db\");"))
				->declareUse("Slim\Container");

			$nameFields = array();
			$idFields = array();
			foreach ($tableColumns as $get) {
				// This is for the getByName selector(s)
				if (stristr($get["Field"], "name"))
					$nameFields[] = $get["Field"];
				// This is for the getByID selector(s)
				if (strstr($get["Field"], "ID"))
					$idFields[] = $get["Field"];
				// This is for the getByHash selector(s)
				if (stristr($get["Field"], "Hash"))
					$idFields[] = $get["Field"];
			}

			// Get generator
			foreach ($nameFields as $name) {
				// get * by Name
				$class->setMethod(PhpMethod::create("getAllBy" . ucfirst($name))
					->addParameter(PhpParameter::create($name)
						->setType("string"))
					->setVisibility("public")
					->setBody("return \$this->db->query(\"SELECT * FROM {$table} WHERE {$name} = :{$name}\", array(\":{$name}\" => \${$name}));")
				);
			}
			foreach ($idFields as $id) {
				// get * by ID,
				$class->setMethod(PhpMethod::create("getAllBy" . ucfirst($id))
					->addParameter(PhpParameter::create($id)
						->setType("int"))
					->setVisibility("public")
					->setBody("return \$this->db->query(\"SELECT * FROM {$table} WHERE {$id} = :{$id}\", array(\":{$id}\" => \${$id}));")
				);
			}
			foreach ($nameFields as $name) {
				foreach ($tableColumns as $get) {
					// If the fields match, skip it.. no reason to get/set allianceID where allianceID = allianceID
					if ($get["Field"] == $name)
						continue;
					// Skip the id field
					if ($get["Field"] == "id")
						continue;
					$class->setMethod(PhpMethod::create("get" . ucfirst($get["Field"]) . "By" . ucfirst($name))
						->addParameter(PhpParameter::create($name)
							->setType("string"))
						->setVisibility("public")
						->setBody("return \$this->db->queryField(\"SELECT {$get["Field"]} FROM {$table} WHERE {$name} = :{$name}\", \"{$get["Field"]}\", array(\":{$name}\" => \${$name}));")
					);
				}
			}
			foreach ($idFields as $id) {
				foreach ($tableColumns as $get) {
					// If the fields match, skip it.. no reason to get/set allianceID where allianceID = allianceID
					if ($get["Field"] == $id)
						continue;
					// Skip the id field
					if ($get["Field"] == "id")
						continue;
					$class->setMethod(PhpMethod::create("get" . ucfirst($get["Field"]) . "By" . ucfirst($id))
						->addParameter(PhpParameter::create($id)
							->setType("int"))
						->setVisibility("public")
						->setBody("return \$this->db->queryField(\"SELECT {$get["Field"]} FROM {$table} WHERE {$id} = :{$id}\", \"{$get["Field"]}\", array(\":{$id}\" => \${$id}));")
					);
				}
			}

			if($input->getOption("updaters") === NULL) {
				$output->writeln("Updaters being generated..");
				foreach ($nameFields as $name) {
					foreach ($tableColumns as $get) {
						// If the fields match, skip it.. no reason to get/set allianceID where allianceID = allianceID
						if ($get["Field"] == $name)
							continue;
						// Skip the id field
						if ($get["Field"] == "id")
							continue;

						$class->setMethod(PhpMethod::create("update" . ucfirst($get["Field"]) . "By" . ucfirst($name))
							->addParameter(PhpParameter::create($get["Field"])
								->setType(stristr($get["Type"], "int") ? "int" : "string"))
							->addParameter(PhpParameter::create($name)
								->setType("string"))
							->setVisibility("public")
							->setBody("\$exists = \$this->db->queryField(\"SELECT {$get["Field"]} FROM {$table} WHERE {$name} = :{$name}\", \"{$get["Field"]}\", array(\":{$name}\" => \${$name}));
if(!empty(\$exists)) {
	\$this->db->execute(\"UPDATE {$table} SET {$get["Field"]} = :{$get["Field"]} WHERE {$name} = :{$name}\", array(\":{$name}\" => \${$name}, \":{$get["Field"]}\" => \${$get["Field"]}));
}
                    ")
						);
					}
				}
				foreach ($idFields as $id) {
					foreach ($tableColumns as $get) {
						// If the fields match, skip it.. no reason to get/set allianceID where allianceID = allianceID
						if ($get["Field"] == $id)
							continue;
						// Skip the id field
						if ($get["Field"] == "id")
							continue;
						$class->setMethod(PhpMethod::create("update" . ucfirst($get["Field"]) . "By" . ucfirst($id))
							->addParameter(PhpParameter::create($get["Field"])
								->setType(stristr($get["Type"], "int") ? "int" : "string"))
							->addParameter(PhpParameter::create($id)
								->setType("int"))
							->setVisibility("public")
							->setBody("\$exists = \$this->db->queryField(\"SELECT {$get["Field"]} FROM {$table} WHERE {$id} = :{$id}\", \"{$get["Field"]}\", array(\":{$id}\" => \${$id})); 
if(!empty(\$exists)) {
	\$this->db->execute(\"UPDATE {$table} SET {$get["Field"]} = :{$get["Field"]} WHERE {$id} = :{$id}\", array(\":{$id}\" => \${$id}, \":{$get["Field"]}\" => \${$get["Field"]}));
}")
						);
					}
				}
			}

			if($input->getOption("inserter") === NULL) {
				$output->writeln("Inserter being generated..");
				$class->setMethod(PhpMethod::create("insertInto" . ucfirst($table)));
				$fields = array();

				foreach($tableColumns as $column) {
					$t = explode("(", $column["Type"]);
					$type = "string";
					switch($t[0]) {
						case "int":
							$type = "int";
							break;
						case "varchar":
							$type = "string";
							break;
						case "mediumtext":
							$type = "string";
							break;
					}
					if($column["Extra"] == "auto_increment")
						continue;
					if(in_array($column["Field"], array("dateAdded", "lastUpdated", "id")))
						continue;

					$fields[] = $column["Field"];
					$class->getMethod("insertInto" . ucfirst($table))
						->addParameter(PhpParameter::create(lcfirst($column["Field"]))
							->setType($type));
				}
				$arrayList = array();
				foreach($fields as $field)
					$arrayList[] = "\":{$field}\" => \${$field}";

				$class->getMethod("insertInto" . ucfirst($table))
				->setVisibility("public")
					->setBody("return \$this->db->execute(\"INSERT INTO {$table} (" . implode(", ", $fields) . ") VALUES (:" . implode(", :", $fields) . ")\", array(" . implode(", ", $arrayList) . "));");
			}

			$generator = new CodeGenerator(array(
				"generateScalarTypeHints" => true,
				"generateReturnTypeHints" => true,
				"generateEmptyDocblock" => false
			));
			$code = $generator->generate($class);

			$code = "<?php\n" . $code;

			file_put_contents($modelPath, $code);
			$output->writeln("Model generated and stored in {$modelPath}");

			// Update dependencies to load the model..
			$dep = file_get_contents(__DIR__ . "/../dependencies.php");

			$containerString = "\n\n\$container[\"{$table}\"] = function(\$c) {
	return new {$qualifiedName}(\$c);
};";
			$dep = $dep . $containerString;
			file_put_contents(__DIR__ . "/../dependencies.php", $dep);
			// @todo move dependencies into individual loadable files, so we can actually check if there is a dependency loader for a model already in existance.
			$output->writeln("Dependencies updated (Remember if you regenerate this model by force - you must fix dependencies.php as well!");

			$output->writeln("Updating phpstorm meta (Same rule as above applies.. BE VIGILANT");
			// Update phpstorm meta..
			$storm = file_get_contents(__DIR__ . "/../../.phpstorm.meta.php");
			$storm = substr($storm, 0, -13) . ",\n\t\t\t\"{$table}\" instanceof {$qualifiedName}" . substr($storm, -13);
			file_put_contents(__DIR__ . "/../../.phpstorm.meta.php", ucfirst($storm));

		}
	}
}