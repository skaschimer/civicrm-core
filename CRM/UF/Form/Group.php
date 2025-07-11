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
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

/**
 *  This class is for UF Group (Profile) configuration.
 */
class CRM_UF_Form_Group extends CRM_Core_Form {

  use CRM_Core_Form_EntityFormTrait;

  /**
   * @var bool
   */
  public $submitOnce = TRUE;

  /**
   * Set entity fields to be assigned to the form.
   */
  protected function setEntityFields(): void {
    $this->entityFields = [
      'title' => ['name' => 'title', 'required' => TRUE],
      'frontend_title' => ['name' => 'frontend_title', 'required' => TRUE],
      'description' => [
        'name' => 'description',
        'help' => ['id' => 'id-description', 'file' => 'CRM/UF/Form/Group.hlp'],
      ],
      'uf_group_type' => [
        'name' => 'uf_group_type',
        'not-auto-addable' => TRUE,
        'help' => ['id' => 'id-used_for', 'file' => 'CRM/UF/Form/Group.hlp'],
      ],
      'cancel_button_text' => [
        'name' => 'cancel_button_text',
        'help' => [
          'id' => 'id-cancel_button_text',
          'file' => 'CRM/UF/Form/Group.hlp',
        ],
        'class' => 'cancel_button_section',
      ],
      'submit_button_text' => [
        'name' => 'submit_button_text',
        'help' => [
          'id' => 'id-submit_button_text',
          'file' => 'CRM/UF/Form/Group.hlp',
        ],
        'class' => '',
      ],
    ];
  }

  /**
   * Explicitly declare the entity api name.
   */
  public function getDefaultEntity() {
    return 'UFGroup';
  }

  /**
   * The title for group.
   *
   * @var int
   */
  protected $_title;
  protected $_groupElement;
  protected $_group;
  protected $_allPanes;

  /**
   * Set variables up before form is built.
   */
  public function preProcess() {
    $this->preventAjaxSubmit();
    // current form id
    $this->_id = $this->get('id');
    if (!$this->_id) {
      $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE, 0);
    }
    $this->assign('gid', $this->_id);
    $this->_group = CRM_Core_PseudoConstant::group();

    if ($this->_action & (CRM_Core_Action::UPDATE | CRM_Core_Action::DELETE)) {
      $title = CRM_Core_BAO_UFGroup::getTitle($this->_id);
      $this->assign('profileTitle', $title);
    }

    // setting title for html page
    if ($this->_action & CRM_Core_Action::UPDATE) {
      $this->setTitle(ts('Profile Settings') . " - $title");
    }
    elseif ($this->_action & (CRM_Core_Action::DISABLE | CRM_Core_Action::DELETE)) {
      $ufGroup['module'] = implode(' , ', CRM_Core_BAO_UFGroup::getUFJoinRecord($this->_id, TRUE));
      $status = 0;
      $status = CRM_Core_BAO_UFGroup::usedByModule($this->_id);
      if ($this->_action & (CRM_Core_Action::DISABLE)) {
        if ($status) {
          $message = 'This profile is currently used for ' . $ufGroup['module'] . '. If you disable the profile - it will be removed from these forms and/or modules. Do you want to continue?';
        }
        else {
          $message = 'Are you sure you want to disable this Profile?';
        }
      }
      else {
        if ($status) {
          $message = 'This profile is currently used for ' . $ufGroup['module'] . '. If you delete the profile - it will be removed from these forms and/or modules. This action cannot be undone. Do you want to continue?';
        }
        else {
          $message = 'Are you sure you want to delete this Profile? This action cannot be undone.';
        }
      }
      $this->assign('message', $message);
    }
    else {
      $this->setTitle(ts('New CiviCRM Profile'));
    }

    $this->assign('uf_group_type_extra', $this->getOtherModuleString());
  }

  /**
   * Build the form object.
   *
   * @return void
   */
  public function buildQuickForm() {
    self::buildQuickEntityForm();
    if ($this->_action & (CRM_Core_Action::DISABLE | CRM_Core_Action::DELETE)) {
      if ($this->_action & (CRM_Core_Action::DISABLE)) {
        $display = 'Disable Profile';
      }
      else {
        $display = 'Delete Profile';
      }
      $this->addButtons([
        [
          'type' => 'next',
          'name' => $display,
          'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
          'isDefault' => TRUE,
        ],
        [
          'type' => 'cancel',
          'name' => ts('Cancel'),
        ],
      ]);
      return;
    }

    //add checkboxes
    $uf_group_type = [];
    $UFGroupType = CRM_Core_SelectValues::ufGroupTypes();
    foreach ($UFGroupType as $key => $value) {
      $uf_group_type[] = $this->createElement('checkbox', $key, NULL, $value);
    }
    $this->addGroup($uf_group_type, 'uf_group_type', ts('Expose To'), '&nbsp;');

    // help text
    $this->add('wysiwyg', 'help_pre', ts('Pre-form Help'), CRM_Core_DAO::getAttribute('CRM_Core_DAO_UFGroup', 'help_post'));
    $this->add('wysiwyg', 'help_post', ts('Post-form Help'), CRM_Core_DAO::getAttribute('CRM_Core_DAO_UFGroup', 'help_post'));

    // is this group active ?
    $this->addElement('advcheckbox', 'is_active', ts('Is this CiviCRM Profile active?'));

    $paneNames = [
      ts('Advanced Settings') => 'buildAdvanceSetting',
    ];

    foreach ($paneNames as $name => $type) {
      if ($this->_id) {
        $dataURL = "&reset=1&action=update&id={$this->_id}&snippet=4&formType={$type}";
      }
      else {
        $dataURL = "&reset=1&action=add&snippet=4&formType={$type}";
      }

      $allPanes[$name] = [
        'url' => CRM_Utils_System::url('civicrm/admin/uf/group/setting', $dataURL),
        'open' => 'false',
        'id' => $type,
      ];

      CRM_UF_Form_AdvanceSetting::$type($this);
    }

    $this->addButtons([
      [
        'type' => 'next',
        'name' => ts('Save'),
        'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);

    $this->addFormRule(['CRM_UF_Form_Group', 'formRule'], $this);
  }

  /**
   * Set default values for the form. Note that in edit/view mode
   * the default values are retrieved from the database
   *
   *
   * @return void
   */
  public function setDefaultValues() {
    $defaults = [];
    $showHide = new CRM_Core_ShowHideBlocks();

    if (!$this->_id) {
      $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE, NULL);
    }

    if ((isset($this->_id))) {
      $params = ['id' => $this->_id];
      CRM_Core_BAO_UFGroup::retrieve($params, $defaults);
      $defaults['group'] = $defaults['limit_listings_group_id'] ?? NULL;
      $defaults['add_contact_to_group'] = $defaults['add_to_group_id'] ?? NULL;
      //get the uf join records for current uf group
      $ufJoinRecords = CRM_Core_BAO_UFGroup::getUFJoinRecord($this->_id);
      foreach ($ufJoinRecords as $key => $value) {
        $checked[$value] = 1;
      }
      $defaults['uf_group_type'] = $checked ?? "";

      $showAdvanced = 0;
      $advFields = [
        'group',
        'post_url',
        'cancel_url',
        'add_captcha',
        'is_map',
        'is_uf_link',
        'is_edit_link',
        'is_update_dupe',
        'is_cms_user',
        'is_proximity_search',
      ];
      foreach ($advFields as $key) {
        if (!empty($defaults[$key])) {
          $showAdvanced = 1;
          $this->_allPanes['Advanced Settings']['open'] = 'true';
          break;
        }
      }
    }
    else {
      $defaults['add_cancel_button'] = 1;
      $defaults['is_active'] = 1;
      $defaults['is_map'] = 0;
      $defaults['is_update_dupe'] = 2;
      $defaults['is_proximity_search'] = 0;
    }
    // Don't assign showHide elements to template in DELETE mode (fields to be shown and hidden don't exist)
    if (!($this->_action & CRM_Core_Action::DELETE) && !($this->_action & CRM_Core_Action::DISABLE)) {
      $showHide->addToTemplate();
    }
    $this->assign('allPanes', $this->_allPanes);
    return $defaults;
  }

  /**
   * Global form rule.
   *
   * @param array $fields
   *   The input form values.
   * @param array $files
   *   The uploaded files if any.
   * @param self $self
   *   Current form object.
   *
   * @return bool|array
   *   true if no errors, else array of errors
   */
  public static function formRule($fields, $files, $self) {
    $errors = [];

    //validate profile title as well as name.
    $title = $fields['title'];
    $name = CRM_Utils_String::munge($title, '_', 56);
    $name .= $self->_id ? '_' . $self->_id : '';
    $query = 'select count(*) from civicrm_uf_group where ( name like %1 ) and id != %2';
    $pCnt = CRM_Core_DAO::singleValueQuery($query, [
      1 => [$name, 'String'],
      2 => [(int) $self->_id, 'Integer'],
    ]);
    if ($pCnt) {
      $errors['title'] = ts('Profile \'%1\' already exists in Database.', [1 => $title]);
    }

    return empty($errors) ? TRUE : $errors;
  }

  /**
   * Process the form.
   *
   * @return void
   */
  public function postProcess() {
    if ($this->_action & CRM_Core_Action::DELETE) {
      $title = CRM_Core_BAO_UFGroup::getTitle($this->_id);
      CRM_Core_BAO_UFGroup::deleteRecord(['id' => $this->_id]);
      CRM_Core_Session::setStatus(ts("Your CiviCRM Profile '%1' has been deleted.", [1 => $title]), ts('Profile Deleted'), 'success');
    }
    elseif ($this->_action & CRM_Core_Action::DISABLE) {
      $ufJoinParams = ['uf_group_id' => $this->_id];
      CRM_Core_BAO_UFGroup::delUFJoin($ufJoinParams);

      CRM_Core_BAO_UFGroup::setIsActive($this->_id, 0);
    }
    else {
      // get the submitted form values.
      $params = $this->controller->exportValues($this->_name);
      if ($this->_action & (CRM_Core_Action::UPDATE)) {
        $params['id'] = $this->_id;
      }
      elseif ($this->_action & CRM_Core_Action::ADD) {
        $session = CRM_Core_Session::singleton();
        $params['created_id'] = $session->get('userID');
        $params['created_date'] = date('YmdHis');
      }

      // create uf group
      $ufGroup = CRM_Core_BAO_UFGroup::add($params);
      $this->_id = $ufGroup->id;
      if (!empty($params['is_active'])) {
        // Make entry in uf join table
        // we use a default weight of 1, weight is only used for specific components such as Events
        CRM_Core_BAO_UFGroup::createUFJoin(1, $params['uf_group_type'] ?? [], $ufGroup->id);
      }
      elseif ($this->_id) {
        // this profile has been set to inactive, delete all corresponding UF Join's
        $ufJoinParams = ['uf_group_id' => $this->_id];
        CRM_Core_BAO_UFGroup::delUFJoin($ufJoinParams);
      }

      if ($this->_action & CRM_Core_Action::UPDATE) {
        $url = CRM_Utils_System::url('civicrm/admin/uf/group', 'reset=1&action=browse');
        CRM_Core_Session::setStatus(ts("Your CiviCRM Profile '%1' has been saved.", [1 => $ufGroup->title]), ts('Profile Saved'), 'success');
      }
      else {
        // Jump directly to adding a field if popups are disabled
        $action = CRM_Core_Resources::singleton()->ajaxPopupsEnabled ? '' : '/add';
        $url = CRM_Utils_System::url("civicrm/admin/uf/group/field$action", 'reset=1&new=1&gid=' . $ufGroup->id . '&action=' . ($action ? 'add' : 'browse'));
        CRM_Core_Session::setStatus(ts('Your CiviCRM Profile \'%1\' has been added. You can add fields to this profile now.',
          [1 => $ufGroup->title]
        ), ts('Profile Added'), 'success');
      }
      $session = CRM_Core_Session::singleton();
      $session->replaceUserContext($url);
    }

    // update cms integration with registration / my account
    CRM_Utils_System::updateCategories();
  }

  /**
   * Set the delete message.
   *
   * We do this from the constructor in order to do a translation.
   */
  public function setDeleteMessage() {
  }

  /**
   * Returns a string describing which forms/modules are using this profile
   * Ex: Used in Forms: CiviContribute (1, 3, 5), CiviEvent (40, 42, 55, 123 and XX more)
   *
   * @return string|null
   */
  protected function getOtherModuleString() {
    $otherModules = \Civi\Api4\UFJoin::get(TRUE)
      ->addWhere('uf_group_id', '=', $this->_id)
      ->addWhere('module', '!=', 'Profile')
      ->addOrderBy('entity_id', 'ASC')
      ->execute();
    if (empty($otherModules)) {
      return NULL;
    }
    $otherModulesTranslations = [
      'on_behalf' => ts('On Behalf Of Organization'),
      'User Account' => ts('User Account'),
      'User Registration' => ts('User Registration'),
      'CiviContribute' => ts('CiviContribute'),
      'CiviEvent' => ts('CiviEvent'),
      'CiviEvent_Additional' => ts('CiviEvent Additional Participant'),
    ];
    $modulesByComponent = [];
    foreach ($otherModules as $join) {
      $modulesByComponent[$join['module']][] = $join['entity_id'];
    }
    $modulesByComponentReformat = [];
    foreach ($modulesByComponent as $key => $vals) {
      $count = count($vals);
      if ($count > 7) {
        // Keep only 5 and say "and X more"
        $vals = array_slice($vals, 0, 5);
        $modulesByComponentReformat[] = ($otherModulesTranslations[$key] ?? $key) . ' (' . implode(', ', $vals) . ' ' . ts('and %count more', ['count' => $count - 5, 'plural' => 'and %count more']) . ')';
      }
      else {
        $modulesByComponentReformat[] = ($otherModulesTranslations[$key] ?? $key) . ' (' . implode(', ', $vals) . ')';
      }
    }
    return implode(', ', $modulesByComponentReformat);
  }

}
