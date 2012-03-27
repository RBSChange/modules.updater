<?php
class updater_BackgroundCheckUpdateTask extends task_SimpleSystemTask
{
	/**
	 * @see task_SimpleSystemTask::execute()
	 */
	protected function execute()
	{
		updater_ModuleService::getInstance()->refreshLangPackToUpdate();
		
		updater_ModuleService::getInstance()->refreshUpgradeList();
	}
}