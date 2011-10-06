<?php
class commands_updater_Migrate extends commands_AbstractChangeCommand
{
	function isHidden()
	{
		return true;
	}
	
	/**
	 * @return String
	 */
	function getUsage()
	{
		return "[apply|download|refresh]";
	}
	

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "Migrate managment";
	}
	
	function validateArgs($params, $options)
	{
		if (count($params) > 0)
		{
			if (!in_array($params[0], array('apply', 'download', 'refresh')))
			{
				return false;
			}
		}
		return true;
	}
	
	function getParameters($completeParamCount, $params, $options, $current)
	{
		if ($completeParamCount == 0)
		{
			return array('apply', 'download', 'refresh');
		}
	}
	
	function executeCheck($params, $options)
	{
		return $this->quitOk("CHECK SUCCESS");
	}
	
	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->loadFramework();	
		if (isset($options['check']))
		{
			return $this->executeCheck($params, $options);
		}
		if (count($params))
		{
			if ($params[0] === 'refresh')
			{
				updater_ModuleService::getInstance()->refreshUpgradeList();
			}
			elseif ($params[0] === 'download')
			{
				$updateTo = updater_ModuleService::getInstance()->getUpgradeTo();
				if (!empty($updateTo))
				{
					return $this->executeDownload($updateTo);
				}
			}
			elseif ($params[0] === 'apply')
			{
				return $this->executeApply();
			}
		}
		$updateTo = updater_ModuleService::getInstance()->getUpgradeTo();
		if (!empty($updateTo))
		{
			return $this->quitOk("Upgrade available: " . $updateTo);
		}
		return $this->quitOk("No Upgrade");
	}
	
	function executeDownload($upgrateTo)
	{
		$bootStrap = $this->getParent()->getBootStrap();
		$upgrateToPath = $bootStrap->installComponent('lib', 'migration', $upgrateTo);				
		if ($upgrateToPath !== null)
		{
			$this->log('Upgrade succefully installed in repository');	
			$migrationFolderPath = f_util_FileUtils::buildWebeditPath('migration');
			$phpFilePath = $migrationFolderPath . '/migrate.php';	
						
			$moduleWebapp = f_util_FileUtils::buildWebeditPath('modules', 'updater', 'webapp', 'migration');
			f_util_FileUtils::cp($moduleWebapp, $migrationFolderPath, f_util_FileUtils::OVERRIDE | f_util_FileUtils::APPEND);
			f_util_FileUtils::cp($upgrateToPath, $migrationFolderPath, f_util_FileUtils::OVERRIDE | f_util_FileUtils::APPEND);

			if (is_readable($phpFilePath))
			{
				$this->log('Migration script is installed localy');
				
				require_once $phpFilePath;
				$migscript = new c_ChangeMigrationScript();
				if ($migscript->check())
				{
					return $this->quitOk("Upgrade ready to apply");
				} 
				return $this->quitError('Upgarde not applicable');
			}
			else
			{
				return $this->quitError('Unable to find migration script: ' . $phpFilePath);
			}	
		}
		else
		{
			return $this->quitError('Unable to download upgrade');
		}
	}
	
	function executeApply()
	{
		$migrationFolderPath = f_util_FileUtils::buildWebeditPath('migration');
		$phpFilePath = $migrationFolderPath . '/migrate.php';
		
		if (is_readable($phpFilePath))
		{
			require_once $phpFilePath;
			$migscript = new c_ChangeMigrationScript();
			if ($migscript->check())
			{
				$this->log('Starting migration...');
				$migscript->main();
				return $this->log('migration finish');
			}
		}

		return $this->quitError('Unable to start script.');
	}
}