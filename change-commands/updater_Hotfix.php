<?php
class commands_updater_Hotfix extends commands_AbstractChangeCommand
{
	/**
	 * @return String
	 */
	function getUsage()
	{
		return "[apply [<number>]]";
	}
	

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "Hotfix managment";
	}
	
	function isHidden()
	{
		return true;
	}
	
	
	function validateArgs($params, $options)
	{
		if (count($params) > 0)
		{
			if ($params[0] !== 'apply')
			{
				return false;
			}
			if (count($params) > 2)
			{
				return false;
			}
			elseif (count($params) == 2 && !is_numeric($params[1]))
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
			return array('apply');
		}
		elseif ($completeParamCount == 1 && $params[0] == 'apply')
		{
			$hf = array_keys($this->getHotfixes(c_ChangeBootStrap::getInstance()));
			if (count($hf))
			{
				return array($hf[0]) ;
			}
		}
		return null;
	}
	
	
	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$hotfixes = $this->getHotfixes($this->getParent()->getBootStrap());		
		if (count($hotfixes) == 0)
		{
			return $this->quitOk("No hotfix available for your project");
		}
		
		if (count($params) === 0)
		{
			return $this->executeList($hotfixes);
		}
		elseif (count($params) === 1)
		{
			return $this->executeApplyAll($hotfixes);
		}
		return $this->executeApply($hotfixes, $params[1]);
		
		$hotfixes = $this->getHotfixes($this->getParent()->getBootStrap());		
		if (count($hotfixes) == 0)
		{
			return $this->quitOk("No hotfix available for your project");
		}
		
		$this->message("You should apply the following hotfixes:");
		foreach ($hotfixes as $hotfixNumber => $hotfixName)
		{
			$this->message('  ' . CHANGE_COMMAND . " apply-hotfix " . $hotfixName);
		}
	}
	
	function executeList($hotfixes)
	{
		$patches = PatchService::getInstance()->check();
		if (count($patches) > 0)
		{
			$message = "Your project must apply the following patches before to apply any hotfix:\n";
			foreach ($patches as $packageName => $patchList)
			{
				$module = str_replace('modules_', '', $packageName);
				foreach ($patchList as $patchName)
				{
					$message .= '  ' . CHANGE_COMMAND . ' apply-patch ' . $module . ' ' . $patchName."\n";
				}
			}
			$this->warnMessage($message);			
		}
		
		$this->message("You should apply the following hotfixes:");
		foreach ($hotfixes as $hotfixNumber => $hotfixName)
		{
			$this->message('  ' . $hotfixName);
		}
		return true;
	}
	
	function executeApplyAll($hotfixes)
	{
		$patches = PatchService::getInstance()->check();	
		if (count($patches) > 0)
		{
			$errStr = "Your project must apply the following patches before to apply any hotfix:\n";
			foreach ($patches as $packageName => $patchList)
			{
				$module = str_replace('modules_', '', $packageName);
				foreach ($patchList as $patchName)
				{
					$errStr .= '  ' . CHANGE_COMMAND . ' apply-patch ' . $module . ' ' . $patchName."\n";
				}
			}
			return $this->quitError($errStr);
		}
		$siteDisabled  = file_exists("site_is_disabled");
		if (!$siteDisabled) {$this->getParent()->executeCommand("disable-site");}
		foreach ($hotfixes as $number => $name) 
		{
			$this->message("Apply hotfix: " . $name);
			try
			{
				echo f_util_System::execChangeCommand('hotfix', array('apply', $number)), PHP_EOL;
			}
			catch (Exception $e)
			{
				$lines = explode(PHP_EOL, $e->getMessage());
				$exceptionMessage = array_shift($lines);
				echo implode(PHP_EOL, $lines), PHP_EOL;
				break;
			}
		}
		if (!$siteDisabled) {$this->getParent()->executeCommand("enable-site");}
	}

	function executeApply($hotfixes, $hotfix)
	{
		$patches = PatchService::getInstance()->check();	
		if (count($patches) > 0)
		{
			$errStr = "Your project must apply the following patches before to apply any hotfix:\n";
			foreach ($patches as $packageName => $patchList)
			{
				$module = str_replace('modules_', '', $packageName);
				foreach ($patchList as $patchName)
				{
					$errStr .= '  ' . CHANGE_COMMAND . ' apply-patch ' . $module . ' ' . $patchName."\n";
				}
			}
			return $this->quitError($errStr);
		}
		
		if (!isset($hotfixes[$hotfix]))
		{
			return $this->quitError("Hotfix $hotfix is not available for your project");
		}
		$tmp = array_keys($hotfixes);
		$firstHotFix = $tmp[0];
		
		if ($firstHotFix != $hotfix)
		{
			return $this->quitError("You must first apply hotfix number $firstHotFix");
		}
		$firstHotfixName = $hotfixes[$hotfix];
		list ($category, $componentName, $version, $hotfix) = explode('/', str_replace('-', '/', $firstHotfixName));
		
		$bootStrap = $this->getParent()->getBootStrap();
		$hotfixPath = $bootStrap->installComponent($category, $componentName, $version, $hotfix);
		if ($hotfixPath === null)
		{
			return $this->quitError("Unable to download hotfix " . $firstHotfixName);
		}
		
		$siteDisabled  = file_exists("site_is_disabled");	
		if (!$siteDisabled) {$this->getParent()->executeCommand("disable-site");}
		
		if (!$bootStrap->linkToProject($category, $componentName, $version, $hotfix))
		{
			if (!$siteDisabled) {$this->getParent()->executeCommand("enable-site");}
			return $this->quitError("Unable to link '$firstHotfixName' in project");
		}
		
		if (!$bootStrap->updateProjectDependencies($category, $componentName, $version, $hotfix))
		{
			if (!$siteDisabled) {$this->getParent()->executeCommand("enable-site");}
			return $this->quitError("Unable to update file project dependencies change.xml");
		}		
		
		$patches = PatchService::resetInstance()->check();
		foreach ($patches as $packageName => $patchList)
		{
			$module = str_replace('modules_', '', $packageName);
			foreach ($patchList as $patchName)
			{
				$this->getParent()->executeCommand("apply-patch", array($module, $patchName));
			}
		}
		
		if (!$siteDisabled) {$this->getParent()->executeCommand("enable-site");}		
		return $this->quitOK("hotfix ".$hotfix." applied successfully");
	}
	
	/**
	 * @return array
	 * @example   3 => '/framework/framework-3.0.3-3',
	 * 		 	  12 => '/framework/framework-3.0.3-12',
	 */
	function getHotfixes($bootStrap)
	{
		$hotfixes = $bootStrap->getHotfixes(Framework::getVersion());
		$computedDeps = $this->getInstalledRepositoryPaths($bootStrap);
		
		$hotfixesFiltered = array();
		foreach ($hotfixes as  $hotfixPath)
		{
			list($hf_depType, $hf_componentName, $hf_version, $hf_hotFix) = $bootStrap->explodeRepositoryPath($hotfixPath);
			$hfKey = $hf_depType .'/'. $hf_componentName .'/'. $hf_version;
			if (isset($computedDeps[$hfKey]) && $hf_hotFix > $computedDeps[$hfKey])
			{
				$hotFixName = $bootStrap->convertToCategory($hf_depType) . '/' . $hf_componentName . '-' . $hf_version . '-' . $hf_hotFix; 
				$hotfixesFiltered[$hf_hotFix] = $hotFixName;
			}
		}
		ksort($hotfixesFiltered, SORT_NUMERIC);		
		return $hotfixesFiltered;
	}
	
	function getInstalledRepositoryPaths($bootStrap)
	{
		$result = array();
		$computedDeps = $bootStrap->getComputedDependencies();	
		foreach ($computedDeps as $category => $components) 
		{
			if (!is_array($components)) {continue;}
			foreach ($components as $componentName => $infos) 
			{
				list($depType, $componentName, $version, $hotFix) = $bootStrap->explodeRepositoryPath($infos['repoRelativePath']);
				$result[$depType .'/'. $componentName .'/'. $version] = $hotFix ? $hotFix : 0;
			}
		}
		return $result;
	}
}