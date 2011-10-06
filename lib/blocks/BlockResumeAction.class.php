<?php
/**
 * updater_BlockResumeAction
 * @package modules.updater.lib.blocks
 */
class updater_BlockResumeAction extends dashboard_BlockDashboardAction
{	
	/**
	 * @param f_mvc_Request $request
	 * @param boolean $forEdition
	 */
	protected function setRequestContent($request, $forEdition)
	{
		if ($forEdition)
		{
			return;
		}
		$ums = updater_ModuleService::getInstance();
		
		$hotfixArray = $ums->getHotfixArray();
		$request->setAttribute('hotfixArray', count($hotfixArray) ? $hotfixArray: null);
		
		$langPackArray = array();
		foreach ($ums->getLangPackToUpdateArray() as $pack => $nbkey) 
		{
			$langPackArray[] = LocaleService::getInstance()->transBO('m.updater.bo.general.block-resume-keys', array('ucf'), 
				array('nbKeys' => $nbkey, 'pack' => $pack));
		}
		$request->setAttribute('langPackArray', count($langPackArray) ? $langPackArray : null);
		
		$patchArray = array();
		foreach (PatchService::getInstance()->check() as $packageName => $value)
		{
			$module = str_replace('modules_', '', $packageName);
			$patchArray[] = $module . ' ' . $value;
		}
		$request->setAttribute('patchArray', count($patchArray) ? $patchArray : null);

		$upgrade = $ums->getUpgradeTo();
		$request->setAttribute('upgradeTo', empty($upgrade) ? null : $upgrade);

		$hasUpdate = count($hotfixArray) || count($langPackArray) || count($patchArray) || (!empty($upgrade));
		
		$request->setAttribute('hasUpdate', $hasUpdate);
	}
}