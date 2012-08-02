<?php
/**
 * @package modules.updater.lib.services
 */
class updater_ModuleService extends ModuleBaseService
{
	/**
	 * Singleton
	 * @var updater_ModuleService
	 */
	private static $instance = null;

	/**
	 * @return updater_ModuleService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	public function refreshUpgradeList()
	{
		
		$upgradeTo = '';
		$doc = $this->getReleaseDocument(Framework::getVersion());
		if ($doc)
		{
			$elem = $doc->findUnique("//lib[@name='migration']");
			if ($elem)
			{
				$upgradeTo = $elem->getAttribute('version');			
			}
		}
		$this->getTransactionManager()->beginTransaction();
		$this->getPersistentProvider()->setSettingValue('modules_updater', 'upgrate_to', $upgradeTo);
		$this->getTransactionManager()->commit();
	}
	
	/**
	 * @param string $releaseName
	 * @return f_util_DOMDocument || null
	 */
	protected function getReleaseDocument($releaseName)
	{
		$bootStrap = new c_ChangeBootStrap(WEBEDIT_HOME);
		foreach ($bootStrap->getRemoteRepositories() as $baseURL) 
		{
			$url = $baseURL . '/release-' . $releaseName .'.xml';
			$client = HTTPClientService::getInstance()->getNewHTTPClient();
			$data = $client->get($url);
			if ($client->getHTTPReturnCode() == 200 && !empty($data))
			{
				try 
				{
					$doc = f_util_DOMUtils::fromString($data);
					$elem = $doc->findUnique("//lib[@name='migration']");
					if ($elem)
					{
						return $doc;
					}
				}
				catch (Exception $e)
				{
					Framework::exception($e);
				}
			}
		}
		return null;
	}
	
	/**
	 * @return string
	 */
	public function getUpgradeTo()
	{
		$upgradeTo  = $this->getPersistentProvider()->getSettingValue('modules_updater', 'upgrate_to');
		return (empty($upgradeTo)) ? null : $upgradeTo;
	}
	
	/**
	 * @return string
	 */
	protected function getLangPackRemoteRepository()
	{
		return Framework::getConfigurationValue('modules/updater/langpackrepository', null);
	}
	
	/**
	 * 
	 */
	public function refreshLangPackToUpdate()
	{
		$remoteRepository = $this->getLangPackRemoteRepository();
		if ($remoteRepository == null)
		{
			return;
		}
		
		$packages = $this->getProjectLangPack();
		$nbKeySetting = array();
		foreach ($packages as $packageName => $baseKey) 
		{
			$url = $this->buildLangPackUrl($baseKey, true);
			if ($url)
			{
				$client = HTTPClientService::getInstance()->getNewHTTPClient();
				$data = $client->get($url);
				if ($client->getHTTPReturnCode() == 200 && !empty($data))
				{
					try 
					{
						$doc = f_util_DOMUtils::fromString($data);
						if ($doc->documentElement && $doc->documentElement->hasAttribute('nbkeys'))
						{
							$nbkeys = $doc->documentElement->getAttribute('nbkeys');
							$nbKeySetting[$packageName] = $nbkeys;
						}
					} 
					catch (Exception $e) 
					{
						Framework::exception($e);
					}
				}
			}
		}	
		
		$this->getTransactionManager()->beginTransaction();
		foreach ($nbKeySetting as $packageName => $nbkeys) 
		{
			$this->getPersistentProvider()->setSettingValue($packageName, 'I18nKeys', $nbkeys);
		}
		$this->getTransactionManager()->commit();
	}
	

	
	/**
	 * @param string $inputBaseKey
	 */
	public function applyLangPack($inputBaseKey)
	{
		$result = array('nbkeys' => 0);
		$packages = $this->getProjectLangPack();
		foreach ($packages as $packageName => $baseKey) 
		{
			if (empty($inputBaseKey) || strpos($baseKey, $inputBaseKey) === 0)
			{
				$url = $this->buildLangPackUrl($baseKey, false);
				if ($url)
				{
					$client = HTTPClientService::getInstance()->getNewHTTPClient();
					$data = $client->get($url);
					if ($client->getHTTPReturnCode() == 200 && !empty($data))
					{
	
						$doc = f_util_DOMUtils::fromString($data);
						if ($doc->documentElement && $doc->documentElement->hasAttribute('nbkeys'))
						{
							$nbkeys = intval($doc->documentElement->getAttribute('nbkeys'));
							if ($nbkeys > 0)
							{
								$this->writeLangPackKeys($doc);
								$lastUpdate = date_Calendar::getInstance()->toString();
								$this->getPersistentProvider()->setSettingValue($packageName, 'I18nLastUpdate', $lastUpdate);
								$this->getPersistentProvider()->setSettingValue($packageName, 'I18nKeys', 0);
								$result['nbkeys'] += $nbkeys;
								if ($baseKey === 'f')
								{
									LocaleService::getInstance()->regenerateLocalesForFramework();
								}
								else
								{
									$name = substr($baseKey, 2);
									if ($baseKey[0] === 'm')
									{
										LocaleService::getInstance()->regenerateLocalesForModule($name);
									}
									elseif ($baseKey[0] === 't')
									{
										LocaleService::getInstance()->regenerateLocalesForTheme($name);
									}
								}
							}
							else
							{
								$this->getPersistentProvider()->setSettingValue($packageName, 'I18nKeys', 0);
							}
						}
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * @param f_util_DOMDocument $keysDoc
	 */
	protected function writeLangPackKeys($keysDoc)
	{
		$ls = LocaleService::getInstance();

		$updates = array();
		foreach ($keysDoc->find('//key') as $keyNode) 
		{
			$cleanKey = $keyNode->getAttribute('id');
			list($baseKey, $id) = $ls->explodeKey($cleanKey);
			if (!isset($updates[$baseKey])) {$updates[$baseKey] = array();}
			
			$lcid = $keyNode->getAttribute('lcid');
			if (!isset($updates[$baseKey][$lcid])) {$updates[$baseKey][$lcid] = array();}
			$format = strtolower($keyNode->getAttribute('format'));
			$content = $keyNode->textContent;			
			$updates[$baseKey][$lcid][$id] = array($content, $format);
		}
		
		foreach ($updates as $baseKey => $keysInfos) 
		{
			$ls->updatePackage($baseKey, $keysInfos);
		}
	}
	
	/**
	 * @return array<$packageName => $nbKeys>
	 */
	public function getLangPackToUpdateArray()
	{
		$result = array();
		$packages = $this->getProjectLangPack();
		foreach ($packages as $packageName => $baseKey) 
		{
			$nbKeys = $this->getPersistentProvider()->getSettingValue($packageName, 'I18nKeys');
			if ($nbKeys !== null && intval($nbKeys) > 0)
			{
				$result[str_replace('_', '/', $packageName)] =  $nbKeys;
			}
		}
		return $result;
	}
	
	/**
	 * @param string $baseKey
	 * @param boolean $check
	 * @return string
	 */
	protected function buildLangPackUrl($baseKey, $check = false)
	{
		$remoteRepository = $this->getLangPackRemoteRepository();
		if ($remoteRepository == null)
		{
			return null;
		}
		
		$version = null;
		if ($baseKey === 'f' || strpos($baseKey, 't.') === 0)
		{
			$version = Framework::getVersion();
			$packageName = $baseKey === 'f' ? 'framework' : 'themes_' . substr($baseKey, 2);
		}
		elseif (strpos($baseKey, 'm.') === 0)
		{
			$cModule = ModuleService::getInstance()->getModule(substr($baseKey, 2));
			$version = $cModule->getVersion();
			$packageName = 'modules_' . substr($baseKey, 2);
		}
		
		if ($version)
		{
			$lastUpdate = $this->getPersistentProvider()->getSettingValue($packageName, 'I18nLastUpdate');
			if (empty($lastUpdate)) {$lastUpdate = '';}
			$lastUpdate = urlencode($lastUpdate);
			$check = $check ? 'true' : 'false';
			return $remoteRepository . '/index.php?module=i18nmanager&action=GetPackage&package=' .$baseKey .'&version=' .$version .'&lastUpdate='.$lastUpdate.'&check=' . $check;
		}
		return null;
	}
	
	/**
	 * @return array
	 */
	protected function getProjectLangPack()
	{
		$package = array('framework' => 'f');
		
		$modulesPaths = 	glob(f_util_FileUtils::buildWebeditPath('modules', '*', 'i18n'));
		if (is_array($modulesPaths))
		{
			foreach ($modulesPaths as $value) 
			{
				$name = basename(dirname($value));
				$package['modules_' . $name] = 'm.' . $name;
			}
		}
		$themesPaths = 	glob(f_util_FileUtils::buildWebeditPath('themes', '*', 'i18n'));
		if (is_array($themesPaths))
		{
			foreach ($themesPaths as $value) 
			{
				$name = basename(dirname($value));
				$package['themes_' . $name] = 't.' . $name;
			}
		}
		return $package;
	}
	
}