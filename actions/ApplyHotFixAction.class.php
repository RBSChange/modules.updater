<?php
/**
 * updater_ApplyHotFixAction
 * @package modules.updater.actions
 */
class updater_ApplyHotFixAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$hotFix = $request->getParameter('hotFix');
		$this->log('Installing hotfix: ' . $hotFix);
		
		if (preg_match('/^.*-([0-9]+)$/', $hotFix, $match))
		{
			$bootstrapPath = f_util_FileUtils::buildFrameworkPath('bin', 'bootstrap.php');
			require_once $bootstrapPath;
			
			list(, $hotFixNumber) = $match;
			$bootStrap = new c_ChangeBootStrap(WEBEDIT_HOME);
			$hotfixes = $this->getHotfixes($bootStrap);			
			if (count($hotfixes) > 0)
			{
				$tmp = array_keys($hotfixes);
				if ($tmp[0] == $hotFixNumber)
				{
					$firstHotfixName = $hotfixes[$hotFixNumber];
					list ($category, $componentName, $version, $hotfix) = explode('/', str_replace('-', '/', $firstHotfixName));
					$hotfixPath = $bootStrap->installComponent($category, $componentName, $version, $hotfix);
					
					if ($hotfixPath !== null)
					{
						$this->log('Hotfix succefully installed in repository');
						
						// require_once on cboot_ClassDirAnalyzer :: constructor
						cboot_ClassDirAnalyzer::getInstance();
						
						if ($bootStrap->linkToProject($category, $componentName, $version, $hotfix))
						{					
							if ($bootStrap->updateProjectDependencies($category, $componentName, $version, $hotfix))
							{
								$bootStrap->cleanDependenciesCache();
								updater_ModuleService::getInstance()->refreshHotFixList();
								$this->log('Hotfix succefully installed');
							}
							else
							{
								$this->log('Unable to update project dependency', 'error');
							}
						}
						else
						{
							$this->log('Unable to link hotfix into the project', 'error');
						}
					}
					else
					{
						$this->log('Unable to download hotfix', 'error');
					}
				}
				else
				{
					$this->log('HotFix can not be installed before :' . $hotfixes[$tmp[0]] , 'error');
					updater_ModuleService::getInstance()->refreshHotFixList();
				}
			}
			else
			{
				$this->log('HotFix not found', 'error');
			}
		}
		
		return $this->sendJSON(array('logs' => $this->logs));
	}
	
	/**
	 * @return array
	 * @example   3 => '/framework/framework-3.0.3-3',
	 * 		 	  12 => '/framework/framework-3.0.3-12',
	 * @param c_ChangeBootStrap $bootStrap
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
	
	/**
	 * 
	 * @param c_ChangeBootStrap $bootStrap
	 */
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
	
	private $logs = array();
	
	/**
	 * used by patch_BasePatch
	 * @param string $string
	 * @param string $level
	 */
	public function log($string, $level = 'info') 
	{
		$this->logs[] = array($level, $string);
	}
	
	function isDocumentAction()
	{
		return false;
	}
}