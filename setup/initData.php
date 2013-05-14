<?php
/**
 * @package modules.updater.setup
 */
class updater_Setup extends object_InitDataSetup
{
	public function install()
	{
		$this->executeModuleScript('init.xml');
		
		$tm = f_persistentdocument_TransactionManager::getInstance();
		$pp = $tm->getPersistentProvider();
		$pp->setDocumentCache(false);

		$ids = users_BackenduserService::getInstance()->createQuery()
			->add(Restrictions::like('dashboardcontent', 'modules_dashboard_browsersversion'))
			->setProjection(Projections::property('id', 'id'))
			->findColumn('id');
		
		$chunks = array_chunk($ids, 100);
		foreach ($chunks as $chunk)
		{
			try
			{
				$tm->beginTransaction();
				
				foreach ($chunk as $id)
				{
					$user = users_persistentdocument_backenduser::getInstanceById($id);
					$dash = $user->getDashboardcontent();
					$newDash = str_replace('type="modules_dashboard_browsersversion"', 'type="modules_updater_Resume"', $dash);
					if ($newDash !== $dash)
					{
						Framework::info('Update: ' . $id .  ' dashboardcontent');
						$user->setDashboardcontent($newDash);
						$pp->updateDocument($user);
					}
				}	
						
				$tm->commit();
			} 
			catch (Exception $e) 
			{
				$tm->rollback($e);
			}
		}
		$pp->setDocumentCache(true);
	}

	/**
	 * @return String[]
	 */
	public function getRequiredPackages()
	{
		// Return an array of packages name if the data you are inserting in
		// this file depend on the data of other packages.
		// Example:
		// return array('modules_website', 'modules_users');
		return array();
	}

}