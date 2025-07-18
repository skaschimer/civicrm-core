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

use Civi\Api4\CustomField;
use Civi\Api4\OptionGroup;

/**
 *  Test APIv3 civicrm_create_custom_group
 *
 * @package   CiviCRM
 * @group headless
 */
class api_v3_CustomFieldTest extends CiviUnitTestCase {

  /**
   * Clean up after test.
   *
   * @throws \CRM_Core_Exception
   */
  public function tearDown(): void {
    // The parent tearDown will delete the linked option group (gender)
    // if we don't do it more carefully here.
    CRM_Core_DAO::executeQuery('UPDATE civicrm_custom_field f
      INNER JOIN civicrm_option_group g ON g.id = option_group_id
      SET option_group_id = NULL WHERE g.name = "gender"');
    CustomField::delete()
      ->addWhere('id', '>', 0)
      ->execute();
    $this->quickCleanup([
      'civicrm_contact',
      'civicrm_file',
      'civicrm_entity_file',
    ], TRUE);
    parent::tearDown();
  }

  /**
   * Check with edit.
   */
  public function testCustomFieldCreateWithEdit(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Individual', 'title' => 'text_test_group']);
    $params = [
      'custom_group_id' => $customGroup['id'],
      'name' => 'test_textfield2',
      'label' => 'Name1',
      'html_type' => 'Text',
      'data_type' => 'String',
      'default_value' => 'abc',
      'weight' => 4,
      'is_required' => 1,
      'is_searchable' => 0,
      'is_active' => 1,
    ];

    $customField = $this->callAPISuccess('custom_field', 'create', $params);
    $customField['label'] = 'Name2';
    $customFieldEdited = $this->callAPISuccess('custom_field', 'create', $customField);

    $this->assertNotNull($customFieldEdited['id']);
  }

  /**
   * Check for Each data type: loop through available form input types
   */
  public function testCustomFieldCreateAllAvailableFormInputs(): void {
    $gid = $this->customGroupCreate(['extends' => 'Individual', 'title' => 'testAllFormInputs']);

    $dtype = $customFieldDataType = array_column(CRM_Core_BAO_CustomField::dataType(), 'id');
    $htype = CRM_Custom_Form_Field::$_dataToHTML;

    // Legacy html types returned by v3
    $htype['StateProvince'] = ['Select State/Province'];
    $htype['Country'] = ['Select Country'];

    foreach ($dtype as $dkey) {
      foreach ($htype[$dkey] as $hvalue) {
        $this->_loopingCustomFieldCreateTest($this->_buildParams($gid['id'], $hvalue, $dkey));
      }
    }
  }

  /*
   * Can't figure out the point of this?
   */

  /**
   * @param array $params
   */
  public function _loopingCustomFieldCreateTest($params) {
    $customField = $this->callAPISuccess('custom_field', 'create', $params);
    $this->assertNotNull($customField['id']);
    $this->getAndCheck($params, $customField['id'], 'CustomField');
  }

  /**
   * @param int $gid
   * @param $htype
   * @param $dtype
   *
   * @return array
   */
  public function _buildParams($gid, $htype, $dtype) {
    $params = [
      'custom_group_id' => $gid,
      'label' => $dtype . $htype,
      'html_type' => $htype,
      'data_type' => $dtype,
      'weight' => 4,
      'is_required' => 0,
      'is_searchable' => 0,
      'is_active' => 1,

    ];

    /* //Not Working for any type. Maybe redundant with testCustomFieldCreateWithOptionValues()
    if ($htype == 'Multi-Select')
    $params = array_merge($params, array(
    'option_label'    => array( 'Label1','Label2'),
    'option_value'    => array( 'val1', 'val2' ),
    'option_weight'   => array( 1, 2),
    'option_status'   => array( 1, 1),
    ));
     */

    return $params;
  }

  /**
   * Check with data type - Options with option_values
   */
  public function testCustomFieldCreateWithEmptyOptionGroup(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Contact', 'title' => 'select_test_group']);
    $params = [
      'custom_group_id' => $customGroup['id'],
      'label' => 'Country',
      'html_type' => 'Select',
      'data_type' => 'String',
      'weight' => 4,
      'is_required' => 1,
      'is_searchable' => 0,
      'is_active' => 1,
    ];

    $customField = $this->callAPISuccess('CustomField', 'create', $params);
    $this->assertNotNull($customField['id']);
    $optionGroupID = $this->callAPISuccess('CustomField', 'getvalue', [
      'id' => $customField['id'],
      'return' => 'option_group_id',
    ]);

    $this->assertTrue(is_numeric($optionGroupID) && ($optionGroupID > 0));
    $optionGroup = $this->callAPISuccess('option_group', 'getsingle', [
      'id' => $optionGroupID,
    ]);
    $this->assertEquals('select_test_group :: Country', $optionGroup['title']);
    $optionValueCount = $this->callAPISuccess('option_value', 'getcount', [
      'option_group_id' => $optionGroupID,
    ]);
    $this->assertEquals(0, $optionValueCount);
  }

  /**
   * Check with non-ascii labels
   */
  public function testCustomFieldCreateWithNonAsciiLabel(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Contact', 'title' => 'select_test_group']);
    $params = [
      'custom_group_id' => $customGroup['id'],
      'label' => 'ôôôô',
      'html_type' => 'Select',
      'data_type' => 'String',
      'weight' => 4,
      'is_required' => 1,
      'is_searchable' => 0,
      'is_active' => 1,
    ];
    $customField = $this->callAPISuccess('CustomField', 'create', $params);
    $this->assertNotNull($customField['id']);
    $params['label'] = 'ààà';
    $customField = $this->callAPISuccess('CustomField', 'create', $params);
    $this->assertNotNull($customField['id']);
  }

  /**
   * Test custom field with existing option group.
   */
  public function testCustomFieldExistingOptionGroup(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Organization', 'title' => 'test_group']);
    $params = [
      'custom_group_id' => $customGroup['id'],
      // Just to say something:
      'label' => 'Organization Gender',
      'html_type' => 'Select',
      'data_type' => 'Int',
      'weight' => 4,
      'is_required' => 1,
      'is_searchable' => 0,
      'is_active' => 1,
      // Option group id 3: gender
      'option_group_id' => 3,
    ];

    $customField = $this->callAPISuccess('custom_field', 'create', $params);
    $this->assertNotNull($customField['id']);
    $optionGroupID = $this->callAPISuccess('custom_field', 'getvalue', [
      'id' => $customField['id'],
      'return' => 'option_group_id',
    ]);

    $this->assertEquals(3, $optionGroupID);
  }

  /**
   * Test adding an optionGroup to an existing field doesn't cause a fatal error.
   *
   * (this was happening due to a check running despite no existing option_group_id)
   *
   * @throws \CRM_Core_Exception
   */
  public function testUpdateCustomFieldAddOptionGroup(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Organization', 'title' => 'test_group']);
    $params = [
      'custom_group_id' => $customGroup['id'],
      'label' => 'Organization Gender',
      'html_type' => 'Text',
      'data_type' => 'Int',
    ];

    $customField = $this->callAPISuccess('custom_field', 'create', $params);
    $this->callAPISuccess('CustomField', 'create', [
      'option_group_id' => civicrm_api3('OptionGroup', 'getvalue', ['options' => ['limit' => 1], 'return' => 'id']),
      'id' => $customField['id'],
      'html_type' => 'Select',
    ]);
  }

  /**
   * Test custom field get works & return param works
   */
  public function testCustomFieldGetReturnOptions(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Individual', 'title' => 'test_group']);
    $customField = $this->customFieldCreate(['custom_group_id' => $customGroup['id']]);

    $result = $this->callAPISuccess('custom_field', 'getsingle', [
      'id' => $customField['id'],
      'return' => 'data_type',
    ]);
    $this->assertArrayHasKey('data_type', $result);
    $this->assertArrayNotHasKey('custom_group_id', $result);
  }

  /**
   * Test custom field get works & return param works
   */
  public function testCustomFieldGetReturnArray(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Individual', 'title' => 'test_group']);
    $customField = $this->customFieldCreate(['custom_group_id' => $customGroup['id']]);

    $result = $this->callAPISuccess('custom_field', 'getsingle', [
      'id' => $customField['id'],
      'return' => ['data_type'],
    ]);
    $this->assertTrue(array_key_exists('data_type', $result));
    $this->assertFalse(array_key_exists('custom_group_id', $result));
  }

  /**
   * Test custom field get works & return param works
   */
  public function testCustomFieldGetReturnTwoOptions(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Individual', 'test_group']);
    $customField = $this->customFieldCreate(['custom_group_id' => $customGroup['id']]);

    $result = $this->callAPISuccess('custom_field', 'getsingle', [
      'id' => $customField['id'],
      'return' => 'data_type, custom_group_id',
    ]);
    $this->assertTrue(array_key_exists('data_type', $result));
    $this->assertTrue(array_key_exists('custom_group_id', $result));
    $this->assertFalse(array_key_exists('label', $result));
  }

  public function testCustomFieldCreateWithOptionValues(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Contact', 'title' => 'select_test_group']);

    $option_values = [
      [
        'weight' => 1,
        'label' => 'Label1',
        'value' => 1,
        'is_active' => 1,
      ],
      [
        'weight' => 2,
        'label' => 'Label2',
        'value' => 2,
        'is_active' => 1,
      ],
    ];

    $params = [
      'custom_group_id' => $customGroup['id'],
      'label' => 'Our special field',
      'html_type' => 'Select',
      'data_type' => 'String',
      'weight' => 4,
      'is_required' => 1,
      'is_searchable' => 0,
      'is_active' => 1,
      'option_values' => $option_values,

    ];

    $customField = $this->callAPISuccess('CustomField', 'create', $params);

    $this->assertAPISuccess($customField);
    $this->assertNotNull($customField['id']);
    $getFieldsParams = [
      'options' => ['get_options' => 'custom_' . $customField['id']],
      'action' => 'create',
    ];
    $fields = $this->callAPISuccess('Contact', 'getfields', $getFieldsParams);
    $this->assertArrayHasKey('options', $fields['values']['custom_' . $customField['id']]);
    $this->assertEquals('Label1', $fields['values']['custom_' . $customField['id']]['options'][1]);
    $getOptionsArray = [
      'field' => 'custom_' . $customField['id'],
    ];
    $result = $this->callAPISuccess('Contact', 'getoptions', $getOptionsArray);
    $this->assertEquals('Label1', $result['values'][1]);
  }

  /**
   * Check That any associated Mapping Field Entries are also removed.
   */
  public function testCustomFieldDeleteWithMappingField(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Individual', 'title' => 'test_group']);
    $customField = $this->customFieldCreate(['custom_group_id' => $customGroup['id']]);
    $this->assertNotNull($customField['id']);
    $mapping = $this->callAPISuccess('Mapping', 'create', [
      'name' => 'test mapping',
      'mapping_type_id' => 'Export Contact',
    ]);
    $mappingField = $this->callAPISuccess('MappingField', 'create', [
      'mapping_id' => $mapping['id'],
      'name' => 'custom_' . $customField['id'],
      'grouping' => 1,
      'column_number' => 0,
    ]);
    $mappingFieldCheck = $this->callAPISuccess('MappingField', 'get', ['mapping_id' => $mapping['id']]);
    $this->assertCount(1, $mappingFieldCheck['values']);
    $params = [
      'id' => $customField['id'],
    ];
    $this->callAPISuccess('custom_field', 'delete', $params);
    $mappingFieldCheck = $this->callAPISuccess('MappingField', 'get', ['mapping_id' => $mapping['id']]);
    $this->assertCount(0, $mappingFieldCheck['values']);
    $this->callAPISuccess('Mapping', 'delete', ['id' => $mapping['id']]);
  }

  /**
   * Check for Option Value.
   */
  public function testCustomFieldOptionValueDelete(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Contact', 'title' => 'ABC']);
    $customOptionValueFields = $this->customFieldOptionValueCreate($customGroup, 'fieldABC');
    $params = [
      'id' => $customOptionValueFields,
    ];

    $customField = $this->callAPISuccess('custom_field', 'delete', $customOptionValueFields);
  }

  /**
   * If there's one custom group for "Contact" and one for "Activity", then "Contact.getfields"
   * and "Activity.getfields" should return only their respective fields (not the other's fields),
   * and unrelated entities should return no custom fields.
   */
  public function testGetfields_CrossEntityPollution(): void {
    $auxEntities = ['Email', 'Address', 'LocBlock', 'Membership', 'ContributionPage', 'ReportInstance'];
    $allEntities = array_merge(['Contact', 'Activity'], $auxEntities);

    // Baseline - getfields doesn't reporting any customfields for any entities
    foreach ($allEntities as $entity) {
      $this->assertEquals(
        [],
        $this->getCustomFieldKeys($this->callAPISuccess($entity, 'getfields', [])),
        "Baseline custom fields for $entity should be empty"
      );
    }

    // Add some fields
    $contactGroup = $this->customGroupCreate(['extends' => 'Contact', 'title' => 'test_group_c']);
    $contactField = $this->customFieldCreate([
      'custom_group_id' => $contactGroup['id'],
      'label' => 'For Contacts',
    ]);
    $indivGroup = $this->customGroupCreate(['extends' => 'Individual', 'title' => 'test_group_i']);
    $indivField = $this->customFieldCreate(['custom_group_id' => $indivGroup['id'], 'label' => 'For Individuals']);
    $activityGroup = $this->customGroupCreate(['extends' => 'Activity', 'title' => 'test_group_a']);
    $activityField = $this->customFieldCreate([
      'custom_group_id' => $activityGroup['id'],
      'label' => 'For Activities',
    ]);

    // Check getfields
    $this->assertEquals(
      ['custom_' . $contactField['id'], 'custom_' . $indivField['id']],
      $this->getCustomFieldKeys($this->callAPISuccess('Contact', 'getfields', [])),
      'Contact custom fields'
    );
    $this->assertEquals(
      ['custom_' . $contactField['id'], 'custom_' . $indivField['id']],
      $this->getCustomFieldKeys($this->callAPISuccess('Individual', 'getfields', [])),
      'Individual custom fields'
    );
    $this->assertEquals(
      ['custom_' . $contactField['id']],
      $this->getCustomFieldKeys($this->callAPISuccess('Organization', 'getfields', [])),
      'Organization custom fields'
    );
    $this->assertEquals(
      ['custom_' . $activityField['id']],
      $this->getCustomFieldKeys($this->callAPISuccess('Activity', 'getfields', [])),
      'Activity custom fields'
    );
    foreach ($auxEntities as $entity) {
      $this->assertEquals(
        [],
        $this->getCustomFieldKeys($this->callAPISuccess($entity, 'getfields', [])),
        "Custom fields for $entity should be empty"
      );
    }
  }

  /**
   * Test setting and getting a custom file field value.
   *
   * Uses the "attachment" api for setting value.
   */
  public function testCustomFileField(): void {
    $customGroup = $this->customGroupCreate(['title' => 'attachment_test_group']);
    $params = [
      'custom_group_id' => $customGroup['id'],
      'name' => 'test_file_attachment',
      'label' => 'test_file_attachment',
      'html_type' => 'File',
      'data_type' => 'File',
      'is_active' => 1,
    ];
    $customField = $this->callAPISuccess('custom_field', 'create', $params);
    $cfId = 'custom_' . $customField['id'];

    $cid = $this->individualCreate();

    $attachment = $this->callAPISuccess('attachment', 'create', [
      'name' => CRM_Utils_String::createRandom(5, CRM_Utils_String::ALPHANUMERIC) . '_testCustomFileField.txt',
      'mime_type' => 'text/plain',
      'content' => 'My test content',
      'field_name' => $cfId,
      'entity_id' => $cid,
    ]);
    $this->assertAttachmentExistence(TRUE, $attachment);

    $result = $this->callAPISuccess('contact', 'getsingle', [
      'id' => $cid,
      'return' => $cfId,
    ]);

    $this->assertEquals($attachment['id'], $result[$cfId]);
  }

  /**
   * Similar to testCustomFileField but
   * (a) We can't create it with attachment api since it will still make
   * entity_file records, and what we're testing is attachment.get when those
   * records aren't present anymore.
   * (b) I forget what b was.
   */
  public function testCustomFileFieldAttachmentGet(): void {
    $customGroup = $this->customGroupCreate(['title' => 'attachment_test_group']);
    $params = [
      'custom_group_id' => $customGroup['id'],
      'name' => 'test_file_attachment',
      'label' => 'test_file_attachment',
      'html_type' => 'File',
      'data_type' => 'File',
      'is_active' => 1,
    ];
    $customField = $this->callAPISuccess('custom_field', 'create', $params);
    $cfId = 'custom_' . $customField['id'];

    $cid = $this->individualCreate();

    $file = \Civi\Api4\File::create(FALSE)
      ->addValue('mime_type', 'text/plain')
      ->addValue('content', 'My test content')
      ->addValue('file_name', 'testCustomFileField.txt')
      ->execute()->first();
    \Civi\Api4\Contact::update(FALSE)
      ->addWhere('id', '=', $cid)
      ->addValue('attachment_test_group.test_file_attachment', $file['id'])
      ->execute();

    $att = $this->callAPISuccess('Attachment', 'getsingle', ['id' => $file['id']]);

    // id doesn't tell us much, but mostly we're just testing it succeeded
    $this->assertEquals($file['id'], $att['id']);
    $this->assertEquals('testCustomFileField.txt', $att['name']);
  }

  public function testUpdateCustomField(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Individual']);
    $params = ['id' => $customGroup['id'], 'is_active' => 0];
    $result = $this->callAPISuccess('CustomGroup', 'create', $params);
    $result = array_shift($result['values']);

    $this->assertEquals(0, $result['is_active']);

    $this->customGroupDelete($customGroup['id']);
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function testCustomFieldCreateWithOptionGroupName(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Individual', 'title' => 'test_custom_group']);
    OptionGroup::create()->setValues(['name' => 'abc'])->execute();
    $params = [
      'custom_group_id' => $customGroup['id'],
      'name' => 'Activity type',
      'label' => 'Activity type',
      'data_type' => 'String',
      'html_type' => 'Select',
      'option_group_id' => 'abc',
    ];
    $this->callAPISuccess('CustomField', 'create', $params);
  }

  /**
   * @param $getFieldsResult
   *
   * @return array
   */
  public function getCustomFieldKeys($getFieldsResult) {
    $isCustom = function ($key) {
      return 0 === strpos($key, 'custom_');
    };
    $r = array_values(array_filter(array_keys($getFieldsResult['values']), $isCustom));
    sort($r);
    return $r;
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function testMakeSearchableContactReferenceFieldUnsearchable(): void {
    $this->customGroupCreate([
      'name' => 'testCustomGroup',
      'title' => 'testCustomGroup',
      'extends' => 'Individual',
    ]);
    $params = [
      'name' => 'testCustomField',
      'label' => 'testCustomField',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'ContactReference',
      'html_type' => 'Autocomplete-Select',
      'is_searchable' => '1',
    ];
    $result = $this->callAPISuccess('CustomField', 'create', $params);
    $params = [
      'id' => $result['id'],
      'is_searchable' => 0,
    ];
    $result = $this->callAPISuccess('CustomField', 'create', $params);
  }

  /**
   * Test disabling a searchable contact reference field.
   */
  public function testDisableSearchableContactReferenceField(): void {
    $customGroup = $this->customGroupCreate([
      'name' => 'testCustomGroup',
      'title' => 'testCustomGroup',
      'extends' => 'Individual',
    ]);
    $params = [
      'name' => 'testCustomField',
      'label' => 'testCustomField',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'ContactReference',
      'html_type' => 'Autocomplete-Select',
      'is_searchable' => '1',
    ];
    $result = $this->callAPISuccess('CustomField', 'create', $params);
    $params = [
      'id' => $result['id'],
      'is_active' => 0,
    ];
    $this->callAPISuccess('CustomField', 'create', $params);
  }

  public function testLegacyHtmlType(): void {
    $customGroup = $this->customGroupCreate([
      'name' => 'testCustomGroup',
      'title' => 'testCustomGroup',
      'extends' => 'Individual',
    ]);
    $f1 = $this->callAPISuccess('CustomField', 'create', [
      'label' => 'SingleSelect',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'String',
      'html_type' => 'Select',
      'option_values' => [1 => 'One', 2 => 'Two'],
    ]);
    $f2 = $this->callAPISuccess('CustomField', 'create', [
      'label' => 'CheckBoxes',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'String',
      'html_type' => 'CheckBox',
      'option_values' => [1 => 'One', 2 => 'Two'],
    ]);
    $f3 = $this->callAPISuccess('CustomField', 'create', [
      'label' => 'MultiSelect',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'String',
      'html_type' => 'Multi-Select',
      'option_values' => [1 => 'One', 2 => 'Two'],
    ]);

    $result = $this->callAPISuccess('CustomField', 'get', [
      'custom_group_id' => 'testCustomGroup',
      'html_type' => 'Multi-Select',
      'sequential' => 1,
    ]);
    $this->assertCount(1, $result['values']);
    $this->assertEquals('MultiSelect', $result['values'][0]['label']);

    $result = $this->callAPISuccess('CustomField', 'get', [
      'custom_group_id' => 'testCustomGroup',
      'html_type' => ['IN' => ['Multi-Select', 'CheckBox']],
      'sequential' => 1,
    ]);
    $this->assertCount(2, $result['values']);

    $result = $this->callAPISuccess('CustomField', 'get', [
      'custom_group_id' => 'testCustomGroup',
      'html_type' => 'Select',
      'sequential' => 1,
    ]);
    $this->assertCount(1, $result['values']);
    $this->assertEquals('SingleSelect', $result['values'][0]['label']);

    $result = $this->callAPISuccess('CustomField', 'get', [
      'custom_group_id' => 'testCustomGroup',
      'html_type' => ['IN' => ['Select']],
      'sequential' => 1,
    ]);
    $this->assertCount(1, $result['values']);
    $this->assertEquals('SingleSelect', $result['values'][0]['label']);
  }

  public function testLegacyStateCountryTypes(): void {
    $customGroup = $this->customGroupCreate([
      'name' => 'testCustomGroup',
      'title' => 'testCustomGroup',
      'extends' => 'Individual',
    ]);
    $f1 = $this->callAPISuccess('CustomField', 'create', [
      'label' => 'CountrySelect',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'Country',
      'html_type' => 'Select Country',
    ]);
    $f2 = $this->callAPISuccess('CustomField', 'create', [
      'label' => 'StateSelect',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'StateProvince',
      'html_type' => 'Select State/Province',
    ]);
    $f3 = $this->callAPISuccess('CustomField', 'create', [
      'label' => 'MultiSelectSP',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'StateProvince',
      'html_type' => 'Multi-Select State/Province',
    ]);
    $f4 = $this->callAPISuccess('CustomField', 'create', [
      'label' => 'MultiSelectCountry',
      'custom_group_id' => 'testCustomGroup',
      'data_type' => 'Country',
      'html_type' => 'Select Country',
      'serialize' => 1,
    ]);

    $result = $this->callAPISuccess('CustomField', 'get', [
      'custom_group_id' => 'testCustomGroup',
      'html_type' => 'Multi-Select State/Province',
      'sequential' => 1,
    ]);
    $this->assertCount(1, $result['values']);
    $this->assertEquals('MultiSelectSP', $result['values'][0]['label']);
    $this->assertEquals('Multi-Select State/Province', $result['values'][0]['html_type']);
    $this->assertEquals('1', $result['values'][0]['serialize']);

    $result = $this->callAPISuccess('CustomField', 'get', [
      'custom_group_id' => 'testCustomGroup',
      'html_type' => 'Multi-Select Country',
      'sequential' => 1,
    ]);
    $this->assertCount(1, $result['values']);
    $this->assertEquals('MultiSelectCountry', $result['values'][0]['label']);
    $this->assertEquals('Multi-Select Country', $result['values'][0]['html_type']);
    $this->assertEquals('1', $result['values'][0]['serialize']);

    $result = $this->callAPISuccess('CustomField', 'get', [
      'custom_group_id' => 'testCustomGroup',
      'html_type' => 'Select Country',
      'sequential' => 1,
    ]);
    $this->assertCount(1, $result['values']);
    $this->assertEquals('CountrySelect', $result['values'][0]['label']);
    $this->assertEquals('Select Country', $result['values'][0]['html_type']);
    $this->assertEquals('0', $result['values'][0]['serialize']);

    $result = $this->callAPISuccess('CustomField', 'get', [
      'custom_group_id' => 'testCustomGroup',
      'html_type' => 'Select State/Province',
      'sequential' => 1,
    ]);
    $this->assertCount(1, $result['values']);
    $this->assertEquals('StateSelect', $result['values'][0]['label']);
    $this->assertEquals('Select State/Province', $result['values'][0]['html_type']);
    $this->assertEquals('0', $result['values'][0]['serialize']);
  }

}
