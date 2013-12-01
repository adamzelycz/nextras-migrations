<?php
namespace Nextras\Migrations\Controllers;

use Nextras\Migrations\Engine;
use Nextras\Migrations\Entities\Group;
use Nextras\Migrations\Printers;


class HttpController extends BaseController
{
	/** @var string */
	private $action;

	/** @var string */
	private $error;

	public function run()
	{
		$this->processArguments();
		$this->executeAction();
	}

	private function processArguments()
	{
		if (isset($_GET['action']))
		{
			if ($_GET['action'] === 'run' || $_GET['action'] === 'css')
			{
				$this->action = $_GET['action'];
			}
			else
			{
				$this->action = 'error';
			}
		}
		else
		{
			$this->action = 'index';
		}

		if ($this->action === 'run')
		{
			if (isset($_GET['groups']) && is_array($_GET['groups']))
			{
				foreach ($_GET['groups'] as $group)
				{
					if (is_string($group))
					{
						if (isset($this->groups[$group]))
						{
							$this->groups[$group]->enabled = TRUE;
						}
						else
						{
							$error = sprintf(
								"Unknown group '%s', the following groups are registered: '%s'",
								$group, implode('\', \'', array_keys($this->groups))
							);
							goto error;
						}
					}
					else
					{
						$error = 'Malformed groups parameter.';
						goto error;
					}
				}
			}
			else
			{
				$error = 'Missing or invalid groups parameter.';
				goto error;
			}

			if (isset($_GET['reset']) && $_GET['reset'] === '1')
			{
				$this->mode = Engine\Runner::MODE_RESET;
			}
		}

		return;

		error:
		$this->action = 'error';
		$this->error = $error;
	}

	private function executeAction()
	{
		$method = 'action' . ucfirst($this->action);
		$this->$method();
	}

	private function actionIndex()
	{
		$combinations = $this->getGroupsCombinations();
		$this->printHeader();

		$modes = array(
			0 => '<h2 class="continue">Continue</h2>',
			1 => '<h2 class="reset">Reset = all tables, views and data will be DESTROYED!</h2>',
		);

		echo "<h1>Migrations</h1>\n";
		foreach ($modes as $reset => $heading)
		{
			echo "$heading\n";
			echo "<ul>\n";
			foreach ($combinations as $combination)
			{
				$query = htmlspecialchars(http_build_query(array('action' => 'run' , 'groups' => $combination, 'reset' => $reset)));
				$text = htmlspecialchars(implode(' + ', $combination));
				echo "\t<li><a href=\"?$query\">Run $text</a>\n";
			}
			echo "</ul>\n\n";
		}
	}

	private function actionRun()
	{
		$groups = $this->registerGroups();
		$groups = implode(' + ', $groups);

		$this->printHeader();
		echo "<h1>Migrations – $groups</h1>\n";
		echo "<div class=\"output\">";
		$this->runner->run($this->mode);
		echo "</div>\n";
	}

	private function actionCss()
	{
		header('Content-Type: text/css', TRUE);
		readfile(__DIR__ . '/templates/main.css');
	}

	private function actionError()
	{
		$this->printHeader();
		echo "<h1>Migrations – error</h1>\n";
		echo "<div class=\"error-message\">" . nl2br(htmlspecialchars($this->error), FALSE) . "</div>\n";
	}

	private function getGroupsCombinations()
	{
		$groups = array();
		$index = 1;
		foreach ($this->groups as $group)
		{
			$groups[$index] = $group;
			$index = ($index << 1);
		}

		$combinations = array();
		for ($i = 1; true; $i++)
		{
			$combination = array();
			foreach ($groups as $key => $group)
			{
				if ($i & $key) $combination[] = $group->name;
			}
			if (empty($combination)) break;
			foreach ($combination as $groupName)
			{
				foreach ($this->groups[$groupName]->dependencies as $dependency)
				{
					if (!in_array($dependency, $combination)) continue 3;
				}
			}
			$combinations[] = $combination;
		}
		return $combinations;
	}

	private function printHeader()
	{
		readfile(__DIR__ . '/templates/header.phtml');
	}

	protected function createPrinter()
	{
		return new Printers\HtmlDump();
	}
}
