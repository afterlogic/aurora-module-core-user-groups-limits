<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroupsLimits;

/**
 * Provides user groups.
 * 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public function init()
	{
		$this->subscribeEvent('Mail::SendMessage::before', array($this, 'onBeforeSendMessage'));
		
		$this->subscribeEvent('Core::Login::after', array($this, 'onAfterLogin'));
		$this->subscribeEvent('CoreUserGroups::DeleteGroups::after', array($this, 'onAfterRemoveDeleteGroups'));
		$this->subscribeEvent('CoreUserGroups::RemoveUsersFromGroup::after', array($this, 'onAfterRemoveUsersFromGroup'));
		$this->subscribeEvent('CoreUserGroups::AddToGroup::after', array($this, 'onAfterAddToGroup'));
		$this->subscribeEvent('CoreUserGroups::SaveGroupsOfUser::after', array($this, 'onAfterSaveGroupsOfUser'));
		
		$this->subscribeEvent('PersonalFiles::GetUserSpaceLimitMb', array($this, 'onGetUserSpaceLimitMb'));
	}
	
	private function getGroupName($iUserId)
	{
		$oCoreUserGroupsDecorator = \Aurora\Modules\CoreUserGroups\Module::Decorator();
		$aUserGroups = $oCoreUserGroupsDecorator->GetGroupNamesOfUser($iUserId);
		$sGroupName = 'Free';
		if (in_array('Pro', $aUserGroups))
		{
			$sGroupName = 'Pro';
		}
		else if (in_array('Standard', $aUserGroups))
		{
			$sGroupName = 'Standard';
		}
		return $sGroupName;
	}
	
	/**
	 * @param array $aArguments
	 * @param mixed $mResult
	 */
	public function onBeforeSendMessage(&$aArguments, &$mResult)
	{
		$iAuthenticatedUserId = \Aurora\System\Api::getAuthenticatedUserId();
		$sGroupName = $this->getGroupName($iAuthenticatedUserId);
		if ($sGroupName === 'Free')
		{
			$aArguments['Text'] .= ($aArguments['IsHtml'] ? '<br />' : "\r\n") . $this->getConfig('MailSignature', '');
		}
	}
	
	public function onGetUserSpaceLimitMb(&$aArgs, &$mResult)
	{
		$sGroupName = $this->getGroupName($aArgs['UserId']);
		$mResult = 500; // 500mb for Free
		if ($sGroupName === 'Standard')
		{
			$mResult = 10 * 1024; // 10GB
		}
		if ($sGroupName === 'Pro')
		{
			$mResult = 20 * 1024; // 20GB
		}
	}
	
	/**
	 * Applies capabilities for just authenticated user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterLogin(&$aArgs, &$mResult)
	{
		if ($mResult !== false)
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User && $oUser->Role === \Aurora\System\Enums\UserRole::NormalUser)
			{
				// DB operations are not allowed for super admin here (DB might not be configured yet)
				$this->setUserCapabilities($oUser);
			}
		}
	}
	
	/**
	 * Applies capabilities for users from deleted groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterRemoveDeleteGroups(&$aArgs, &$mResult)
	{
		if (is_array($mResult) && !empty($mResult))
		{
			$this->setUserListCapabilities($mResult);
		}
	}
	
	/**
	 * Applies capabilities for users removed from groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterRemoveUsersFromGroup(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities($aArgs['UsersIds']);
		}
	}
	
	/**
	 * Applies capabilities for users removed from groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterAddToGroup(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities($aArgs['UsersIds']);
		}
	}
	
	/**
	 * Applies capabilities for user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterSaveGroupsOfUser(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities([$aArgs['UserId']]);
		}
	}
	
	/**
	 * Applies capabilities for users.
	 * @param array $aUsersIds
	 */
	protected function setUserListCapabilities($aUsersIds)
	{
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		$aFilters = ['EntityId' => [$aUsersIds, 'IN']];
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$aUsers = [];
		if ($oCoreDecorator && !empty($aUsersIds))
		{
			$aUsers = $oEavManager->getEntities(\Aurora\Modules\Core\Classes\User::class, [], 0, 0, $aFilters, 'PublicId', \Aurora\System\Enums\SortOrder::ASC);
		}
		
		foreach ($aUsers as $oUser)
		{
			$this->setUserCapabilities($oUser);
		}
	}
	
	/**
	 * Applies capabilities for user.
	 * @param \Aurora\Modules\Core\Classes\User $oUser
	 */
	protected function setUserCapabilities($oUser)
	{
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$sGroupName = $this->getGroupName($oUser->EntityId);
			$iMailQuota = 100; // 100mb
			if ($sGroupName === 'Standard')
			{
				$iMailQuota = 5 * 1024; // 5GB
			}
			if ($sGroupName === 'Pro')
			{
				$iMailQuota = 20 * 1024; // 20GB
			}
			
			$oCpanelIntegratorDecorator = \Aurora\Modules\CpanelIntegrator\Module::Decorator();
			if ($oCpanelIntegratorDecorator)
			{
				$oCpanelIntegratorDecorator->SetMailQuota($oUser->PublicId, $iMailQuota);
			}
		}
	}
}
