<?php

use CRM_CivicrmAdminUi_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_ACL_Roles',
    'entity' => 'SavedSearch',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'ACL_Roles',
        'label' => E::ts('ACL Roles'),
        'form_values' => NULL,
        'mapping_id' => NULL,
        'search_custom_id' => NULL,
        'api_entity' => 'ACLEntityRole',
        'api_params' => [
          'version' => 4,
          'select' => [
            'acl_role_id:label',
            'ACLEntityRole_Group_entity_id_01.title',
            'is_active',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'join' => [
            [
              'Group AS ACLEntityRole_Group_entity_id_01',
              'LEFT',
              [
                'entity_id',
                '=',
                'ACLEntityRole_Group_entity_id_01.id',
              ],
              [
                'entity_table',
                '=',
                "'civicrm_group'",
              ],
            ],
          ],
          'having' => [],
        ],
        'expires_date' => NULL,
        'description' => NULL,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_ACL_Roles_SearchDisplay_ACL_Roles_Table_1',
    'entity' => 'SearchDisplay',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'ACL_Roles_Table_1',
        'label' => E::ts('AdminUI ACL Roles Table'),
        'saved_search_id.name' => 'ACL_Roles',
        'type' => 'table',
        'settings' => [
          'actions' => TRUE,
          'description' => NULL,
          'sort' => [],
          'limit' => 50,
          'pager' => [
            'show_count' => FALSE,
            'expose_limit' => FALSE,
            'hide_single' => TRUE,
          ],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'acl_role_id:label',
              'label' => E::ts('ACL Role'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'ACLEntityRole_Group_entity_id_01.title',
              'label' => E::ts('Assigned to'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'is_active',
              'label' => E::ts('Enabled'),
              'sortable' => TRUE,
              'editable' => TRUE,
            ],
            [
              'text' => '',
              'style' => 'default',
              'size' => 'btn-xs',
              'icon' => 'fa-bars',
              'links' => [
                [
                  'entity' => 'ACLEntityRole',
                  'action' => 'update',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-pencil',
                  'text' => E::ts('Edit'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'condition' => [],
                ],
                [
                  'task' => 'enable',
                  'entity' => 'ACLEntityRole',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-toggle-on',
                  'text' => E::ts('Enable'),
                  'style' => 'default',
                  'path' => '',
                  'action' => '',
                  'condition' => [],
                ],
                [
                  'task' => 'disable',
                  'entity' => 'ACLEntityRole',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-toggle-off',
                  'text' => E::ts('Disable'),
                  'style' => 'default',
                  'path' => '',
                  'action' => '',
                  'condition' => [],
                ],
                [
                  'entity' => 'ACLEntityRole',
                  'action' => 'delete',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-trash',
                  'text' => E::ts('Delete'),
                  'style' => 'danger',
                  'path' => '',
                  'task' => '',
                  'condition' => [],
                ],
              ],
              'type' => 'menu',
              'icon' => 'fa-bars',
              'alignment' => 'text-right',
            ],
          ],
          'classes' => [
            'table',
            'table-striped',
            'crm-sticky-header',
          ],
          'cssRules' => [
            [
              'disabled',
              'is_active',
              '=',
              FALSE,
            ],
          ],
          'toolbar' => [
            [
              'entity' => 'ACLEntityRole',
              'action' => 'add',
              'target' => 'crm-popup',
              'style' => 'primary',
              'text' => E::ts('Add ACL Role Assignment'),
              'icon' => 'fa-plus',
            ],
          ],
        ],
        'acl_bypass' => FALSE,
      ],
      'match' => [
        'name',
        'saved_search_id',
      ],
    ],
  ],
];
