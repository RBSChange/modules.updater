<?php
/**
 * updater_LoadUpdateAction
 * @package modules.updater.actions
 */
class updater_LoadUpdateAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$ums = updater_ModuleService::getInstance();
		if ($request->getParameter('forcerefresh'))
		{
			$ums->refreshHotFixList();	
			$ums->refreshLangPackToUpdate();
			$ums->refreshUpgradeList();
		}
		
		$result = array();
		$result['langPack'] = array();
		foreach ($ums->getLangPackToUpdateArray() as $packName => $nkKeys)
		{
			if ($packName === 'framework')
			{
				$label =  'Framework';
				$baseKey = 'f';
			}
			else
			{
				list ($type, $module) = explode('/', $packName);
				$label = LocaleService::getInstance()->transBO('m.'.$module.'.bo.general.module-name', array('ucf'));
				$baseKey = $type[0] . '.' . $module;
			}
			$result['langPack'][] = array($baseKey, $nkKeys, $label);
		}
		$result['hasLangPack'] = count($result['langPack']) > 0;
		
		$result['patch'] = array();
		foreach (PatchService::getInstance()->check() as $packageName => $value)
		{
			$module = str_replace('modules_', '', $packageName);			
			if ($module === 'framework')
			{	
				$label =  'Framework';
			}
			else
			{	
				$label =  LocaleService::getInstance()->transBO('m.'.$module.'.bo.general.module-name', array('ucf'));
			}
			foreach ($value as $patchNumber)
			{
				$result['patch'][] = array($module, $patchNumber, $label);
			}
		}
		$result['hasPatch'] = count($result['patch']) > 0;
		
		
		$result['hotFix'] = $ums->getHotfixArray();
		$result['hasHotFix'] = count($result['hotFix']) > 0;
		
		
		$result['upgrade'] = $ums->getUpgradeTo();
		$result['hasUpgrade'] = ! (empty($result['upgrade']));
		
		$result['isReady'] =  !$result['hasHotFix'] && !$result['hasPatch'] && !$result['hasUpgrade'];
		if (!$result['isReady'])
		{
			$result['headerMsg'] = LocaleService::getInstance()->transBO('m.updater.bo.general.header-warning');
		}
		elseif ($result['hasLangPack'])
		{
			$result['headerMsg'] = LocaleService::getInstance()->transBO('m.updater.bo.general.header-langupdate');
		}
		else
		{
			$result['headerMsg'] = LocaleService::getInstance()->transBO('m.updater.bo.general.header-ready');
			
		}
		return $this->sendJSON($result);
	}
}