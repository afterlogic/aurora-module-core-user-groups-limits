<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroupsLimits;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property bool $IncludeInMobile
 * @property bool $IncludeInDesktop
 * @property array $GroupsLimits
 * @property array $BusinessTenantLimits
 * @property array $GroupwareModules
 * @property array $ReservedList
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "",
            ),
            "IncludeInMobile" => new SettingsProperty(
                true,
                "bool",
                null,
                "",
            ),
            "IncludeInDesktop" => new SettingsProperty(
                true,
                "bool",
                null,
                "",
            ),
            "GroupsLimits" => new SettingsProperty(
                [
                    [
                        "GroupName" => "Free",
                        "EmailSendLimitPerDay" => 150,
                        "MailSignature" => "Sent with PrivateMail",
                        "MailQuotaMb" => 500,
                        "FilesQuotaMb" => 500,
                        "AllowMobileApps" => false,
                        "BannerUrlMobile" => "static/styles/images/modules/CoreUserGroupsLimits/upgrade_button.png",
                        "BannerUrlDesktop" => "static/styles/images/modules/CoreUserGroupsLimits/upgrade_button_large.png",
                        "BannerLink" => "https://privatemail.com/members/supporttickets.php",
                        "MaxAllowedActiveAliasCount" => 2,
                        "AliasCreationIntervalDays" => 7
                    
                    ],
                    [

                        "GroupName" => "Standard",
                        "EmailSendLimitPerDay" => 2500,
                        "MailSignature" => "",
                        "MailQuotaMb" => 10240,
                        "FilesQuotaMb" => 10240,
                        "AllowMobileApps" => true,
                        "BannerUrlMobile" => "",
                        "BannerUrlDesktop" => "",
                        "BannerLink" => "",
                        "MaxAllowedActiveAliasCount" => 2,
                        "AliasCreationIntervalDays" => 7
                    ],
                    [
                        "GroupName" => "Pro",
                        "EmailSendLimitPerDay" => 5000,
                        "MailSignature" => "",
                        "MailQuotaMb" => 20480,
                        "FilesQuotaMb" => 20480,
                        "AllowMobileApps" => true,
                        "BannerUrlMobile" => "",
                        "BannerUrlDesktop" => "",
                        "BannerLink" => "",
                        "MaxAllowedActiveAliasCount" => 2,
                        "AliasCreationIntervalDays" => 7
                    ],
                ],
                "array",
                null,
                "",
            ),
            "BusinessTenantLimits" => new SettingsProperty(
                [
                    [
                        "AliasesCount" => 20,
                        "EmailAccountsCount" => 10,
                        "MailStorageQuotaMb" => 102400,
                        "FilesStorageQuotaMb" => 102400
                    ]
                ],
                "array",
                null,
                "",
            ),
            "GroupwareModules" => new SettingsProperty(
                [
                    "CorporateCalendar",
                    "S3CorporateFilestorage",
                    "SharedContacts",
                    "SharedFiles",
                    "TeamContacts",
                    "OutlookSyncWebclient"
                ],
                "array",
                null,
                "",
            ),
            "ReservedList" => new SettingsProperty(
                [],
                "array",
                null,
                "",
            ),
        ];
    }
}
