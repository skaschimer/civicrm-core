<?php

use Civi\Api4\ActionSchedule;
use Civi\Api4\MessageTemplate;

/**
 * @group headless
 */
class CRM_Upgrade_Incremental_BaseTest extends CiviUnitTestCase {
  use CRMTraits_Custom_CustomDataTrait;

  public function tearDown(): void {
    $this->quickCleanup(['civicrm_saved_search', 'civicrm_action_schedule']);
    $this->revertTemplateToReservedTemplate();
    parent::tearDown();
  }

  /**
   * Test message upgrade process.
   *
   * @throws \CRM_Core_Exception
   */
  public function testMessageTemplateUpgrade(): void {
    $templates = $this->callAPISuccess('MessageTemplate', 'get', ['workflow_name' => 'membership_online_receipt'])['values'];
    foreach ($templates as $template) {
      $this->callAPISuccess('MessageTemplate', 'create', ['msg_html' => 'great what a cool member you are', 'id' => $template['id']]);
      $msg_html = $this->callAPISuccessGetValue('MessageTemplate', ['id' => $template['id'], 'return' => 'msg_html']);
      $this->assertEquals('great what a cool member you are', $msg_html);
    }
    $messageTemplateObject = new CRM_Upgrade_Incremental_MessageTemplates('5.4.alpha1');
    $messageTemplateObject->updateTemplates();

    foreach ($templates as $template) {
      $msg_html = MessageTemplate::get()->addWhere('id', '=', $template['id'])->execute()->first()['msg_html'];
      $this->assertStringContainsString('{assign var="greeting" value="{contact.email_greeting_display}"}{if $greeting}<p>{$greeting},</p>{/if}', $msg_html);
    }
  }

  /**
   * Test that a string replacement in a message template can be done.
   *
   * @throws \CRM_Core_Exception
   */
  public function testMessageTemplateStringReplace(): void {
    MessageTemplate::update()->setValues(['msg_html' => '{$display_name}'])->addWhere(
      'workflow_name', '=', 'contribution_invoice_receipt'
    )->execute();
    $upgrader = new CRM_Upgrade_Incremental_MessageTemplates('5.41.0');
    $check = new CRM_Utils_Check_Component_Tokens();
    $message = $check->checkTokens()[0];
    $this->assertEquals('<p>You are using tokens that have been removed or deprecated.</p><ul><li>Please review your contribution_invoice_receipt message template and remove references to the token {$display_name} as it has been replaced by {contact.display_name}</li></ul></p>', $message->getMessage());
    $upgrader->replaceTokenInTemplate('contribution_invoice_receipt', '$display_name', 'contact.display_name');
    $templates = MessageTemplate::get()->addSelect('msg_html')
      ->addWhere(
        'workflow_name', '=', 'contribution_invoice_receipt'
      )->execute();
    foreach ($templates as $template) {
      $this->assertEquals('{contact.display_name}', $template['msg_html']);
    }
    $messages = $check->checkTokens();
    $this->assertEmpty($messages);
    $this->revertTemplateToReservedTemplate('contribution_invoice_receipt');
  }

  /**
   * Test that a $this->string replacement in a message template can be done.
   *
   * @throws \CRM_Core_Exception
   */
  public function testActionScheduleStringReplace(): void {
    ActionSchedule::create(FALSE)->setValues([
      'title' => 'schedule',
      'absolute_date' => '2021-01-01',
      'start_action_date' => '2021-01-01',
      'mapping_id' => 1,
      'entity_value' => 1,
      'body_text' => 'blah {contribution.status}',
      'body_html' => 'blah {contribution.status}',
      'subject' => 'blah {contribution.status}',
    ])->execute();

    $upgrader = new CRM_Upgrade_Incremental_MessageTemplates('5.41.0');
    $upgrader->replaceTokenInActionSchedule('contribution.status', 'contribution.contribution_status_id:label');
    $templates = ActionSchedule::get()->addSelect('body_html', 'subject', 'body_text')->execute();
    foreach ($templates as $template) {
      $this->assertEquals('blah {contribution.contribution_status_id:label}', $template['body_html']);
      $this->assertEquals('blah {contribution.contribution_status_id:label}', $template['body_text']);
      $this->assertEquals('blah {contribution.contribution_status_id:label}', $template['subject']);
    }
  }

  /**
   * Test message upgrade process only edits the default if the template is customised.
   */
  public function testMessageTemplateUpgradeAlreadyCustomised(): void {
    $templates = $this->callAPISuccess('MessageTemplate', 'get', ['workflow_name' => 'membership_online_receipt'])['values'];
    foreach ($templates as $template) {
      if ($template['is_reserved']) {
        $this->callAPISuccess('MessageTemplate', 'create', ['msg_html' => 'great what a cool member you are', 'id' => $template['id']]);
      }
      else {
        $this->callAPISuccess('MessageTemplate', 'create', ['msg_html' => 'great what a silly sausage you are', 'id' => $template['id']]);
      }
    }
    $messageTemplateObject = new CRM_Upgrade_Incremental_MessageTemplates('5.4.alpha1');
    $messageTemplateObject->updateTemplates();

    foreach ($templates as $template) {
      $msg_html = MessageTemplate::get()->addWhere('id', '=', $template['id'])->execute()->first()['msg_html'];
      if ($template['is_reserved']) {
        $this->assertStringContainsString('{assign var="greeting" value="{contact.email_greeting_display}"}{if $greeting}<p>{$greeting},</p>{/if}', $msg_html);
      }
      else {
        $this->assertEquals('great what a silly sausage you are', $msg_html);
      }
    }
  }

  /**
   * Test function for messages on upgrade.
   *
   * @throws \CRM_Core_Exception
   */
  public function testMessageTemplateGetUpgradeMessages(): void {
    MessageTemplate::update(FALSE)
      ->addValue('msg_text', 'Edited text')
      ->addWhere('workflow_name', '=', 'contribution_online_receipt')
      ->addWhere('is_default', '=', TRUE)
      ->execute();
    $messageTemplateObject = new CRM_Upgrade_Incremental_MessageTemplates('5.43.alpha1');
    $messages = $messageTemplateObject->getUpgradeMessages('5.40');
    $this->assertEquals([
      'Contributions - Receipt (on-line)' => 'Missed templates from earlier versions',
    ], $messages);
  }

  /**
   * Test converting a datepicker field.
   */
  public function testSmartGroupDatePickerConversion(): void {
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
         ['grant_application_received_date_high', '=', '01/20/2019'],
         ['grant_due_date_low', '=', '01/22/2019'],
      ],
    ]);
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups();
    $smartGroupConversionObject->updateGroups([
      'datepickerConversion' => [
        'grant_application_received_date',
        'grant_decision_date',
        'grant_money_transfer_date',
        'grant_due_date',
      ],
    ]);
    $savedSearch = $this->callAPISuccessGetSingle('SavedSearch', []);
    $this->assertEquals('grant_application_received_date_high', $savedSearch['form_values'][0][0]);
    $this->assertEquals('2019-01-20 23:59:59', $savedSearch['form_values'][0][2]);
    $this->assertEquals('grant_due_date_low', $savedSearch['form_values'][1][0]);
    $this->assertEquals('2019-01-22 00:00:00', $savedSearch['form_values'][1][2]);
    $hasRelative = FALSE;
    foreach ($savedSearch['form_values'] as $form_value) {
      if ($form_value[0] === 'grant_due_date_relative') {
        $hasRelative = TRUE;
      }
    }
    $this->assertEquals(TRUE, $hasRelative);
  }

  /**
   * Test Multiple Relative Date conversions
   */
  public function testSmartGroupMultipleRelativeDateConversions(): void {
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['membership_join_date_low', '=', '20190903000000'],
        ['membership_join_date_high', '=', '20190903235959'],
        ['membership_start_date_low', '=' , '20190901000000'],
        ['membership_start_date_high', '=', '20190907235959'],
        ['membership_end_date_low', '=', '20190901000000'],
        ['membership_end_date_high', '=', '20190907235959'],
        'relative_dates' => [
          'member_join' => 'this.day',
          'member_start' => 'this.week',
          'member_end' => 'this.week',
        ],
      ],
    ]);
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups();
    $smartGroupConversionObject->updateGroups([
      'datepickerConversion' => [
        'membership_join_date',
        'membership_start_date',
        'membership_end_date',
      ],
    ]);
    $savedSearch = $this->callAPISuccessGetSingle('SavedSearch', []);
    $this->assertContainsEquals('6', array_keys($savedSearch['form_values']));
    $this->assertEquals('membership_join_date_relative', $savedSearch['form_values'][6][0]);
    $this->assertEquals('this.day', $savedSearch['form_values'][6][2]);
    $this->assertContainsEquals('7', array_keys($savedSearch['form_values']));
    $this->assertEquals('membership_start_date_relative', $savedSearch['form_values'][7][0]);
    $this->assertEquals('this.week', $savedSearch['form_values'][7][2]);
    $this->assertContainsEquals('8', array_keys($savedSearch['form_values']));
    $this->assertEquals('membership_end_date_relative', $savedSearch['form_values'][8][0]);
    $this->assertEquals('this.week', $savedSearch['form_values'][8][2]);
  }

  /**
   * Test upgrading multiple Event smart groups of different formats
   */
  public function testMultipleEventSmartGroupDateConversions(): void {
    $savedSearchIds = [];
    $savedSearchIds[] = $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['event_start_date_low', '=', '20191001000000'],
        ['event_end_date_high', '=', '20191031235959'],
        'relative_dates' => [
          'event' => 'this.month',
        ],
      ],
    ])['id'];
    $savedSearchIds[] = $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['event_start_date_low', '=', '20191001000000'],
      ],
    ])['id'];
    $savedSearchIds[] = $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        'event_start_date_low' => '20191001000000',
        'event_end_date_high' => '20191031235959',
        'event_relative' => 'this.month',
      ],
    ])['id'];
    $savedSearchIds[] = $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        'event_start_date_low' => '10/01/2019',
        'event_end_date_high' => '',
        'event_relative' => '0',
      ],
    ])['id'];
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups();
    $smartGroupConversionObject->renameFields([
      ['old' => 'event_start_date_low', 'new' => 'event_low'],
      ['old' => 'event_end_date_high', 'new' => 'event_high'],
    ]);
    $smartGroupConversionObject->updateGroups([
      'datepickerConversion' => [
        'event',
      ],
    ]);
    $expectedResults = [
      $savedSearchIds[0] => [
        'relative_dates' => [],
        2 => ['event_relative', '=', 'this.month'],
      ],
      $savedSearchIds[1] => [
        0 => ['event_low', '=', '2019-10-01 00:00:00'],
        1 => ['event_relative', '=', 0],
      ],
      $savedSearchIds[2] => [
        'event_relative' => 'this.month',
      ],
      $savedSearchIds[3] => [
        'event_relative' => 0,
        'event_low' => '2019-10-01 00:00:00',
      ],
    ];
    $savedSearches = $this->callAPISuccess('SavedSearch', 'get', [
      'id' => ['IN' => $savedSearchIds],
    ]);
    foreach ($savedSearches['values'] as $id => $savedSearch) {
      $this->assertEquals($expectedResults[$id], $savedSearch['form_values']);
    }
  }

  /**
   * Test Log Date conversion
   */
  public function testLogDateConversion(): void {
    // Create two sets of searches one set for added by and one for modified by
    // Each set contains a relative search on this.month and a specific date search low
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['log_date', '=', 1],
        ['log_date_low', '=', '20191001000000'],
        ['log_date_high', '=', '20191031235959'],
        'relative_dates' => [
          'log' => 'this.month',
        ],
      ],
    ]);
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['log_date', '=', 1],
        ['log_date_low', '=', '20191001000000'],
      ],
    ]);
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['log_date', '=', 2],
        ['log_date_low', '=', '20191001000000'],
        ['log_date_high', '=', '20191031235959'],
        'relative_dates' => [
          'log' => 'this.month',
        ],
      ],
    ]);
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['log_date', '=', 2],
        ['log_date_low', '=', '20191001000000'],
      ],
    ]);
    // On the original search form you didn't need to select the log_date radio
    // If it wasn't selected it defaulted to created_date filtering.
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['log_date_low', '=', '20191001000000'],
        ['log_date_high', '=', '20191031235959'],
        'relative_dates' => [
          'log' => 'this.month',
        ],
      ],
    ]);
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['log_date_low', '=', '20191001000000'],
      ],
    ]);
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups();
    $smartGroupConversionObject->renameLogFields();
    $smartGroupConversionObject->updateGroups([
      'datepickerConversion' => [
        'created_date',
        'modified_date',
      ],
    ]);
    $savedSearhes = $this->callAPISuccess('SavedSearch', 'get', []);
    $expectedResults = [
      1 => [
        0 => ['log_date', '=', 1],
        'relative_dates' => [],
        3 => ['created_date_relative', '=', 'this.month'],
      ],
      2 => [
        0 => ['log_date', '=', 1],
        1 => ['created_date_low', '=', '2019-10-01 00:00:00'],
        2 => ['created_date_relative', '=', 0],
      ],
      3 => [
        0 => ['log_date', '=', 2],
        'relative_dates' => [],
        3 => ['modified_date_relative', '=', 'this.month'],
      ],
      4 => [
        0 => ['log_date', '=', 2],
        1 => ['modified_date_low', '=', '2019-10-01 00:00:00'],
        2 => ['modified_date_relative', '=', 0],
      ],
      5 => [
        'relative_dates' => [],
        2 => ['created_date_relative', '=', 'this.month'],
      ],
      6 => [
        0 => ['created_date_low', '=', '2019-10-01 00:00:00'],
        1 => ['created_date_relative', '=', 0],
      ],
    ];
  }

  /**
   * Test converting relationship fields
   */
  public function testSmartGroupRelationshipDateConversions(): void {
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['relationship_start_date_low', '=', '20191001000000'],
        ['relationship_start_date_high', '=', '20191031235959'],
        ['relationship_end_date_low', '=', '20191001000000'],
        ['relationship_end_date_high', '=', '20191031235959'],
        'relative_dates' => [
          'relation_start' => 'this.month',
          'relation_end' => 'this.month',
        ],
      ],
    ]);
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups();
    $smartGroupConversionObject->updateGroups([
      'datepickerConversion' => [
        'relationship_start_date',
        'relationship_end_date',
      ],
    ]);
    $savedSearch = $this->callAPISuccessGetSingle('SavedSearch', []);
    $this->assertEquals([], $savedSearch['form_values']['relative_dates']);
    $this->assertEquals(['relationship_start_date_relative', '=', 'this.month'], $savedSearch['form_values'][4]);
    $this->assertEquals(['relationship_end_date_relative', '=', 'this.month'], $savedSearch['form_values'][5]);
  }

  /**
   * Test convert custom saved search
   */
  public function testSmartGroupCustomDateRangeSearch(): void {
    $this->createCustomGroupWithFieldOfType([], 'date');
    $dateCustomFieldName = $this->getCustomFieldName('date');
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        [$dateCustomFieldName . '_relative', '=', 0],
        [$dateCustomFieldName, '=', ['BETWEEN' => ['20191001000000', '20191031235959']]],
      ],
    ]);
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        [$dateCustomFieldName . '_relative', '=', 0],
        [$dateCustomFieldName, '=', ['>=' => '20191001000000']],
      ],
    ]);
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        [$dateCustomFieldName . '_relative', '=', 0],
        [$dateCustomFieldName, '=', ['<=' => '20191031235959']],
      ],
    ]);
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        [$dateCustomFieldName . '_relative', '=', 'this.month'],
      ],
    ]);
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups();
    $smartGroupConversionObject->convertCustomSmartGroups();
    $expectedResults = [
      1 => [
        0 => [$dateCustomFieldName . '_relative', '=', 0],
        2 => [$dateCustomFieldName . '_low', '=', '2019-10-01 00:00:00'],
        3 => [$dateCustomFieldName . '_high', '=', '2019-10-31 23:59:59'],
      ],
      2 => [
        0 => [$dateCustomFieldName . '_relative', '=', 0],
        2 => [$dateCustomFieldName . '_low', '=', '2019-10-01 00:00:00'],
      ],
      3 => [
        0 => [$dateCustomFieldName . '_relative', '=', 0],
        2 => [$dateCustomFieldName . '_high', '=', '2019-10-31 23:59:59'],
      ],
      4 => [
        0 => [$dateCustomFieldName . '_relative', '=', 'this.month'],
      ],
    ];
    $savedSearches = $this->callAPISuccess('SavedSearch', 'get', []);
    foreach ($savedSearches['values'] as $id => $savedSearch) {
      $this->assertEquals($expectedResults[$id], $savedSearch['form_values']);
    }
  }

  /**
   * Test conversion of on hold group.
   */
  public function testOnHoldConversion(): void {
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['on_hold', '=', '1'],
      ],
    ]);
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups('5.11.alpha1');
    $smartGroupConversionObject->convertEqualsStringToInArray('on_hold');
    $savedSearch = $this->callAPISuccessGetSingle('SavedSearch', []);
    $this->assertEquals('IN', $savedSearch['form_values'][0][1]);
    $this->assertEquals(['1'], $savedSearch['form_values'][0][2]);

  }

  /**
   * Test renaming a field.
   */
  public function testRenameField(): void {
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['activity_date_low', '=', '01/22/2019'],
      ],
    ]);
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups();
    $smartGroupConversionObject->renameField('activity_date_low', 'activity_date_time_low');
    $savedSearch = $this->callAPISuccessGetSingle('SavedSearch', []);
    $this->assertEquals('activity_date_time_low', $savedSearch['form_values'][0][0]);
  }

  /**
   * Test renaming multiple fields.
   *
   * @throws Exception
   */
  public function testRenameFields(): void {
    $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => [
        ['activity_date_low', '=', '01/22/2019'],
        ['activity_date_relative', '=', 0],
      ],
    ]);
    $smartGroupConversionObject = new CRM_Upgrade_Incremental_SmartGroups();
    $smartGroupConversionObject->renameFields([
      ['old' => 'activity_date_low', 'new' => 'activity_date_time_low'],
      ['old' => 'activity_date_relative', 'new' => 'activity_date_time_relative'],
    ]);
    $savedSearch = $this->callAPISuccessGetSingle('SavedSearch', []);
    $this->assertEquals('activity_date_time_low', $savedSearch['form_values'][0][0]);
    $this->assertEquals('activity_date_time_relative', $savedSearch['form_values'][1][0]);
  }

  /**
   * Test that a mis-saved variable in 'contribute settings' can be converted to a
   * 'proper' setting.
   */
  public function testConvertUpgradeContributeSettings(): void {
    $setting = [
      'deferred_revenue_enabled' => 1,
      'invoice_prefix' => 'G_',
      'credit_notes_prefix' => 'XX_',
      'due_date' => '20',
      'due_date_period' => 'weeks',
      'notes' => '<p>Give me money</p>',
      'tax_term' => 'Extortion',
      'tax_display_settings' => 'Exclusive',
    ];
    CRM_Core_DAO::executeQuery("INSERT INTO civicrm_setting (name, domain_id, value)
    VALUES ('contribution_invoice_settings', 1, '" . serialize($setting) . "')");
    CRM_Upgrade_Incremental_Base::updateContributeSettings(NULL, 5.1);
    $this->assertEquals(1, Civi::settings()->get('deferred_revenue_enabled'));
    $this->assertEquals('G_', Civi::settings()->get('invoice_prefix'));
    $this->assertEquals('XX_', Civi::settings()->get('credit_notes_prefix'));
    $this->assertEquals('20', Civi::settings()->get('invoice_due_date'));
    $this->assertEquals('weeks', Civi::settings()->get('invoice_due_date_period'));
    $this->assertEquals('<p>Give me money</p>', Civi::settings()->get('invoice_notes'));
    $this->assertEquals('Extortion', Civi::settings()->get('tax_term'));
    $this->assertEquals('Exclusive', Civi::settings()->get('tax_display_settings'));
  }

  /**
   * dev/core#1405 Test fixing option groups with spaces in the name
   */
  public function testFixOptionGroupName(): void {
    $name = 'This is a test Name';
    $fixedName = CRM_Utils_String::titleToVar(strtolower($name));
    $optionGroup = $this->callAPISuccess('OptionGroup', 'create', [
      'title' => 'Test Option Group',
      'name' => $name,
    ]);
    // API is hardened to strip the spaces to lets re-add in now
    CRM_Core_DAO::executeQuery('UPDATE civicrm_option_group SET name = %1 WHERE id = %2', [
      1 => [$name, 'String'],
      2 => [$optionGroup['id'], 'Positive'],
    ]);
    $preUpgrade = $this->callAPISuccess('OptionGroup', 'getsingle', ['id' => $optionGroup['id']]);
    $this->assertEquals($name, $preUpgrade['name']);
    CRM_Upgrade_Incremental_php_FiveTwentyOne::fixOptionGroupName();
    $postUpgrade = $this->callAPISuccess('OptionGroup', 'getsingle', ['id' => $optionGroup['id']]);
    $this->assertEquals($fixedName, $postUpgrade['name'], 'Ensure that the spaces have been removed from OptionGroup name');
    $this->assertEquals($postUpgrade['name'], $optionGroup['values'][$optionGroup['id']]['name'], 'Ensure that the fixed name matches what the API would produce');
    $this->callAPISuccess('OptionGroup', 'delete', ['id' => $optionGroup['id']]);
  }

  /**
   * Test that if there is an option group name as the same as the proposed fix name that doesn't cause a hard fail in the upgrade
   */
  public function testFixOptionGroupNameWithFixedNameInDatabase(): void {
    $name = 'This is a test Name';
    $fixedName = CRM_Utils_String::titleToVar(strtolower($name));
    $optionGroup = $this->callAPISuccess('OptionGroup', 'create', [
      'title' => 'Test Option Group',
      'name' => $name,
    ]);
    // API is hardened to strip the spaces to lets re-add in now
    CRM_Core_DAO::executeQuery('UPDATE civicrm_option_group SET name = %1 WHERE id = %2', [
      1 => [$name, 'String'],
      2 => [$optionGroup['id'], 'Positive'],
    ]);
    $optionGroup2 = $this->callAPISuccess('OptionGroup', 'create', [
      'title' => 'Test Option Group 2',
      'name' => $name,
    ]);
    $preUpgrade = $this->callAPISuccess('OptionGroup', 'getsingle', ['id' => $optionGroup['id']]);
    $this->assertEquals($name, $preUpgrade['name']);
    $preUpgrade = $this->callAPISuccess('OptionGroup', 'getsingle', ['id' => $optionGroup2['id']]);
    $this->assertEquals($fixedName, $preUpgrade['name']);
    CRM_Upgrade_Incremental_php_FiveTwentyOne::fixOptionGroupName();
    $this->callAPISuccess('OptionGroup', 'delete', ['id' => $optionGroup['id']]);
    $this->callAPISuccess('OptionGroup', 'delete', ['id' => $optionGroup2['id']]);
  }

  /**
   * Test conversion between jcalendar and datepicker in reports
   */
  public function testReportFormConvertDatePicker(): void {
    $report = $this->callAPISuccess('ReportInstance', 'create', [
      'report_id' => 'contribute/detail',
      'form_values' => [
        'fields' => [
          'sort_name' => 1,
          'email' => 1,
          'phone' => 1,
          'financial_type_id' => 1,
          'receive_date' => 1,
          'total_amount' => 1,
          'country_id' => 1,
        ],
        'sort_name_op' => 'has',
        'sort_name_value' => '',
        'id_min' => '',
        'id_max' => '',
        'id_op' => 'lte',
        'id_value' => '',
        'contribution_or_soft_op' => 'eq',
        'contribution_or_soft_value' => 'contributions_only',
        'receive_date_relative' => 0,
        'receive_date_from' => '11/01/1991',
        'receive_date_to' => '',
        'thankyou_date_relative' => '',
        'thankyou_date_from' => '',
        'thankyou_date_to' => '',
        'contribution_source_op' => 'has',
        'contribution_source_value' => '',
        'currency_op' => 'in',
        'currency_value' => [],
        'contribution_status_id_value' => [0 => 1],
        'cancel_date_relative' => '',
        'cancel_date_from' => '',
        'cancel_date_to' => '',
        'cancel_reason_op' => 'has',
        'cancel_reason_value' => '',
        'soft_credit_type_id_op' => 'in',
        'soft_credit_type_id_value' => [],
        'card_type_id_op' => 'in',
        'card_type_id_value' => [],
        'tagid_op' => 'in',
        'tagid_value' => [],
        'gid_op' => 'in',
        'gid_value' => [],
        'group_bys' => ['contribution_id' => 1],
        'order_bys' => [
          1 => [
            'column' => 'sort_name',
            'order' => 'ASC',
          ],
        ],
        'description' => 'Lists specific contributions by criteria including contact, time period, contribution type, contributor location, etc. Contribution summary report points to this report for contribution details.',
        'email_subject' => '',
        'email_to' => '',
        'email_cc' => '',
        'row_count' => '',
        'view_mode' => 'criteria',
        'cache_minutes' => 60,
        'permission' => 'access CiviContribute',
        'parent_id' => '',
        'radio_ts' => '',
        'groups' => '',
        'report_id' => 'contribute/detail',
      ],
      'title' => 'test Report',
    ]);
    CRM_Upgrade_Incremental_php_FiveTwentyFive::convertReportsJcalendarToDatePicker();
    $reportGet = $this->callAPISuccess('ReportInstance', 'getsingle', ['id' => $report['id']]);
    $formValues = @unserialize($reportGet['form_values']);
    $this->assertEquals('1991-11-01 00:00:00', $formValues['receive_date_from']);
  }

  public function testUpdateContactTypeNameField(): void {
    CRM_Core_DAO::executeQuery("INSERT INTO civicrm_contact_type (name,label,parent_id, is_active) VALUES ('', 'Test Contact Type', 1, 1)");
    CRM_Upgrade_Incremental_php_FiveTwentyEight::populateMissingContactTypeName();
    $contactType = $this->callAPISuccess('ContactType', 'getsingle', ['label' => 'Test Contact Type']);
    $this->assertNotEmpty($contactType['name']);
    $this->callAPISuccess('ContactType', 'delete', ['id' => $contactType['id']]);
  }

  public function testUpdateRelationshipCacheTable(): void {
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_relationship_cache DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci");
    CRM_Upgrade_Incremental_php_FiveFortyThree::fixRelationshipCacheTableCollation();
    $contactTableCollation = CRM_Core_BAO_SchemaHandler::getInUseCollation();
    $dao = CRM_Core_DAO::executeQuery('SHOW TABLE STATUS LIKE \'civicrm_relationship_cache\'');
    $dao->fetch();
    $relationshipCacheCollation = $dao->Collation;
    $this->assertEquals($contactTableCollation, $relationshipCacheCollation);
  }

}
