<?php
/**
 * updater_ApplyPatchAction
 * @package modules.updater.actions
 */
class updater_ApplyPatchAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		if ($request->getParameter('init') == 'true')
		{
			PatchService::getInstance()->updateRepository();
			$this->log('Patch repository successfully updated');
		}
		else
		{
			$patch = $request->getParameter('patch');
			if (preg_match('/^([a-z0-9]+) ([0-9]{4})$/', $patch, $match))
			{
				list(, $moduleName, $patchNumber) = $match;
				// Get a instance of class
				$className = $moduleName . '_patch_' . $patchNumber;
				if ($moduleName == "framework")
				{
					$patchPath = f_util_FileUtils::buildWebeditPath($moduleName, "patch", $patchNumber, "install.php");
				}
				else
				{
					$patchPath = f_util_FileUtils::buildWebeditPath("modules", $moduleName, "patch", $patchNumber, "install.php");
				}
				
				if (file_exists($patchPath))
				{
					require_once($patchPath);
					if (class_exists($className, false))
					{
	
						ob_start();
						
						//Constructor called with this for logging
						$patch = new $className($this);
						$patch->executePatch();
						PatchService::getInstance()->patchApply($moduleName, $patchNumber, $patch->isCodePatch());
						
						$result = ob_get_clean();
						if (!empty($result)) 
						{
							array_unshift($this->logs, array('info', $result));
						}
					}
				}
			}
		}
		
		return $this->sendJSON(array('logs' => $this->logs));
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