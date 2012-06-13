<?php
/**
 * updater_DownloadUpgradeAction
 * @package modules.updater.actions
 */
class updater_DownloadUpgradeAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$upgrateTo = $request->getParameter('upgrateto');
		$checked = false;

		if (empty($upgrateTo))
		{

			$this->log('Invalid upgrade: ' . $upgrateTo, 'error');
		}
		else
		{
			$this->log('Installing upgrade: ' . $upgrateTo);
			$bootstrapPath = f_util_FileUtils::buildFrameworkPath('bin', 'bootstrap.php');
			require_once $bootstrapPath;
			
			$bootStrap = new c_ChangeBootStrap(WEBEDIT_HOME);
			// require_once on cboot_ClassDirAnalyzer :: constructor
			cboot_ClassDirAnalyzer::getInstance();
						
			try
			{
				ob_start();
				$url = null;
				
				$upgrateToPath = $bootStrap->downloadDependency(c_ChangeBootStrap::$DEP_LIB, 'migration', $upgrateTo, $url);
				
				$this->log('Upgrade succefully installed in repository from: ' . $url);
				$migrationFolderPath = f_util_FileUtils::buildWebeditPath('migration');
				$phpFilePath = $migrationFolderPath . '/migrateweb.php';
					
				$moduleWebapp = f_util_FileUtils::buildWebeditPath('modules', 'updater', 'webapp', 'migration');
				f_util_FileUtils::cp($moduleWebapp, $migrationFolderPath, f_util_FileUtils::OVERRIDE | f_util_FileUtils::APPEND);
				f_util_FileUtils::cp($upgrateToPath, $migrationFolderPath, f_util_FileUtils::OVERRIDE | f_util_FileUtils::APPEND);
				
				if (is_readable($phpFilePath))
				{
					$this->log('Migration script is installed localy');
					$url = Framework::getUIBaseUrl() . '/migration/migrateweb.php?check=true';
					$client = HTTPClientService::getInstance()->getNewHTTPClient();
						
					$client->setOption(CURLOPT_SSL_VERIFYPEER, FALSE);
					$client->setOption(CURLOPT_SSL_VERIFYHOST, FALSE);
						
					$data = $client->get($url);
					if ($client->getHTTPReturnCode() == 200 && !empty($data))
					{
						$result = JsonService::getInstance()->decode($data);
						if (is_array($result) && isset($result['checked']))
						{
							$result['logs'] = array_merge($this->logs, $result['logs']);
							ob_get_clean();
							return $this->sendJSON($result);
						}
						$this->log('Error on check: ' . $data, 'error');
					}
					$this->log('Unable to check: migrateweb.php error code(' . $client->getHTTPReturnCode() . ')', 'error');
				}
				else
				{
					$this->log('Unable to find migration script: ' . $phpFilePath, 'error');
				}
				ob_get_clean();
			} 
			catch (Exception $e) 
			{
				ob_get_clean();
				$this->log('Unable to download upgrade', 'error');
			}			
		}
		
		return $this->sendJSON(array('logs' => $this->logs, 'checked' => $checked));
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