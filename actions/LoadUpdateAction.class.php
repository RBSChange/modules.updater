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
		$result['langPack'] = $ums->getLangPackToUpdateArray();
		$result['hasLangPack'] = count($result['langPack']) > 0;
		
		$result['hotFix'] = $ums->getHotfixArray();
		$result['hasHotFix'] = count($result['hotFix']) > 0;
		
		$result['patch'] = array();
		foreach (PatchService::getInstance()->check() as $packageName => $value)
		{
			$module = str_replace('modules_', '', $packageName);
			$result['patch'][$module] = $value;
		}
		$result['hasPatch'] = count($result['patch']) > 0;
		
		$result['upgrade'] = $ums->getUpgradeTo();
		$result['hasUpgrade'] = ! (empty($result['upgrade']));
		
		return $this->sendJSON($result);
	}
}