<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * Upgrade logic for FiveFortyThree
 */
class CRM_Upgrade_Incremental_php_FiveFortyThree extends CRM_Upgrade_Incremental_Base {

  /**
   * Compute any messages which should be displayed beforeupgrade.
   *
   * Note: This function is called iteratively for each incremental upgrade step.
   * There must be a concrete step (eg 'X.Y.Z.mysql.tpl' or 'upgrade_X_Y_Z()').
   *
   * @param string $preUpgradeMessage
   * @param string $rev
   *   a version number, e.g. '4.4.alpha1', '4.4.beta3', '4.4.0'.
   * @param null $currentVer
   */
  public function setPreUpgradeMessage(&$preUpgradeMessage, $rev, $currentVer = NULL) {
    // Example: Generate a pre-upgrade message.
    if ($rev === '5.43.alpha1' && !empty(CRM_Core_Component::getEnabledComponents()['CiviCase'])) {
      $preUpgradeMessage .= '<p>' . ts('Minor changes have been made to how the tokens to render case.is_deleted, case.created_date and case.modified_date. See https://docs.civicrm.org/sysadmin/en/latest/upgrade/version-specific/ for more') . '</p>';
    }
  }

  /**
   * Compute any messages which should be displayed after upgrade.
   *
   * Note: This function is called iteratively for each incremental upgrade step.
   * There must be a concrete step (eg 'X.Y.Z.mysql.tpl' or 'upgrade_X_Y_Z()').
   *
   * @param string $postUpgradeMessage
   *   alterable.
   * @param string $rev
   *   an intermediate version; note that setPostUpgradeMessage is called repeatedly with different $revs.
   */
  public function setPostUpgradeMessage(&$postUpgradeMessage, $rev) {
    // Example: Generate a post-upgrade message.
    // if ($rev == '5.12.34') {
    //   $postUpgradeMessage .= '<br /><br />' . ts("By default, CiviCRM now disables the ability to import directly from SQL. To use this feature, you must explicitly grant permission 'import SQL datasource'.");
    // }
  }

  /**
   * Upgrade function.
   *
   * @param string $rev
   */
  public function upgrade_5_43_alpha1(string $rev): void {
    $this->addTask(ts('Upgrade DB to %1: SQL', [1 => $rev]), 'runSql', $rev);
    $this->addTask('Fix DB Collation if needed on the relatonship cache table', 'fixRelationshipCacheTableCollation');
    $this->addTask('Make mapping field foreign key cascade delete', 'alterMappingFK');
    $this->addTask('Replace legacy displayName smarty token in Online contribution workflow template',
      'updateMessageToken', 'contribution_online_receipt', '$displayName', 'contact.display_name', $rev
    );
    $this->addTask('Replace legacy first_name smarty token in Online contribution workflow template',
      'updateMessageToken', 'contribution_online_receipt', '$first_name', 'contact.first_name', $rev
    );
    $this->addTask('Replace legacy last_name smarty token in Online contribution workflow template',
      'updateMessageToken', 'contribution_online_receipt', '$last_name', 'contact.last_name', $rev
    );
    $this->addTask('Replace membership status token in action schedule',
      'updateActionScheduleToken', 'membership.status', 'membership.status_id:label', $rev
    );
    $this->addTask('Replace membership type token in action schedule',
      'updateActionScheduleToken', 'membership.type', 'membership.membership_type_id:label', $rev
    );
  }

  /**
   * @param \CRM_Queue_TaskContext $ctx
   *
   * @return bool
   */
  public static function alterMappingFK(CRM_Queue_TaskContext $ctx): bool {
    CRM_Core_BAO_SchemaHandler::safeRemoveFK('civicrm_mapping_field', 'FK_civicrm_mapping_field_mapping_id');
    CRM_Core_DAO::executeQuery('
        ALTER TABLE civicrm_mapping_field
        ADD CONSTRAINT `FK_civicrm_mapping_field_mapping_id`
        FOREIGN KEY (`mapping_id`)
        REFERENCES `civicrm_mapping`(`id`) ON DELETE CASCADE
      ', [], TRUE, NULL, FALSE, FALSE);
    return TRUE;
  }

  public static function fixRelationshipCacheTableCollation():bool {
    $contactTableCollation = CRM_Core_BAO_SchemaHandler::getInUseCollation();
    $dao = CRM_Core_DAO::executeQuery('SHOW TABLE STATUS LIKE \'civicrm_relationship_cache\'');
    $dao->fetch();
    $relationshipCacheCollation = $dao->Collation;
    $characterSet = 'utf8';
    if (stripos($contactTableCollation, 'utf8mb4') !== FALSE) {
      $characterSet = 'utf8mb4';
    }
    if ($contactTableCollation !== $relationshipCacheCollation) {
      CRM_Core_BAO_SchemaHandler::migrateUtf8mb4(($characterSet === 'utf8mb4' ? FALSE : TRUE), ['%civicrm_relationship_cache%']);
    }
    return TRUE;
  }

}