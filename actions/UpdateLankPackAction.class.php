<?php
/**
 * updater_UpdateLankPackAction
 * @package modules.updater.actions
 */
class updater_UpdateLankPackAction extends f_action_BaseJSONAction
{
	/**
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$result = array('nbkeys' => 0);
		$baseKey = $request->getParameter('baseKey');
		if (empty($baseKey)) {$baseKey = '';}		
		$result = updater_ModuleService::getInstance()->applyLangPack($baseKey);
		return $this->sendJSON($result);
	}
	
	function isDocumentAction()
	{
		return false;
	}
}