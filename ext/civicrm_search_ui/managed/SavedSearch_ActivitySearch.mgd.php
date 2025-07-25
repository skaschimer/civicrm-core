<?php
use CRM_CivicrmSearchUi_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_ActivitySearch',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'ActivitySearch',
        'label' => E::ts('Search Activities'),
        'api_entity' => 'Activity',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'activity_type_id:label',
            'subject',
            'activity_date_time',
            'status_id:label',
            'GROUP_CONCAT(DISTINCT Activity_ActivityContact_Contact_02.sort_name) AS GROUP_CONCAT_Activity_ActivityContact_Contact_02_sort_name',
            'GROUP_CONCAT(DISTINCT Activity_ActivityContact_Contact_01.sort_name) AS GROUP_CONCAT_Activity_ActivityContact_Contact_01_sort_name',
            'GROUP_CONCAT(DISTINCT Activity_ActivityContact_Contact_03.sort_name) AS GROUP_CONCAT_Activity_ActivityContact_Contact_03_sort_name',
            'ISNOTNULL(parent_id) AS ISNOTNULL_parent_id',
          ],
          'orderBy' => [],
          'where' => [
            ['is_deleted', '=', FALSE],
          ],
          'groupBy' => ['id'],
          'join' => [
            [
              'Contact AS Activity_ActivityContact_Contact_01',
              'LEFT',
              'ActivityContact',
              [
                'id',
                '=',
                'Activity_ActivityContact_Contact_01.activity_id',
              ],
              [
                'Activity_ActivityContact_Contact_01.record_type_id:name',
                '=',
                '"Activity Targets"',
              ],
            ],
            [
              'Contact AS Activity_ActivityContact_Contact_02',
              'LEFT',
              'ActivityContact',
              [
                'id',
                '=',
                'Activity_ActivityContact_Contact_02.activity_id',
              ],
              [
                'Activity_ActivityContact_Contact_02.record_type_id:name',
                '=',
                '"Activity Source"',
              ],
            ],
            [
              'Contact AS Activity_ActivityContact_Contact_03',
              'LEFT',
              'ActivityContact',
              [
                'id',
                '=',
                'Activity_ActivityContact_Contact_03.activity_id',
              ],
              [
                'Activity_ActivityContact_Contact_03.record_type_id:name',
                '=',
                '"Activity Assignees"',
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'SavedSearch_ActivitySearch_SearchDisplay_Search_Activities',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Search_Activities',
        'label' => E::ts('Find Activities'),
        'saved_search_id.name' => 'ActivitySearch',
        'type' => 'table',
        'settings' => [
          'description' => E::ts(NULL),
          'sort' => [
            [
              'activity_date_time',
              'DESC',
            ],
            [
              'Activity_ActivityContact_Contact_01.sort_name',
              'ASC',
            ],
          ],
          'limit' => 50,
          'pager' => [
            'expose_limit' => TRUE,
            'show_count' => TRUE,
            'hide_single' => TRUE,
          ],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'activity_type_id:label',
              'label' => E::ts('Type'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'subject',
              'label' => E::ts('Subject'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_Activity_ActivityContact_Contact_02_sort_name',
              'label' => E::ts('Added by'),
              'sortable' => TRUE,
              'link' => [
                'path' => '',
                'entity' => 'Contact',
                'action' => 'view',
                'join' => 'Activity_ActivityContact_Contact_02',
                'target' => '',
                'task' => '',
              ],
              'title' => E::ts('View Activity Contacts 2'),
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_Activity_ActivityContact_Contact_01_sort_name',
              'label' => E::ts('With'),
              'sortable' => TRUE,
              'link' => [
                'path' => '',
                'entity' => 'Contact',
                'action' => 'view',
                'join' => 'Activity_ActivityContact_Contact_01',
                'target' => '',
                'task' => '',
              ],
              'title' => E::ts('View Activity Contacts'),
            ],
            [
              'type' => 'field',
              'key' => 'GROUP_CONCAT_Activity_ActivityContact_Contact_03_sort_name',
              'label' => E::ts('Assigned'),
              'sortable' => TRUE,
              'link' => [
                'path' => '',
                'entity' => 'Contact',
                'action' => 'view',
                'join' => 'Activity_ActivityContact_Contact_03',
                'target' => '',
                'task' => '',
              ],
              'title' => E::ts('View Activity Contacts 3'),
            ],
            [
              'type' => 'field',
              'key' => 'activity_date_time',
              'label' => E::ts('Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'status_id:label',
              'label' => E::ts('Status'),
              'sortable' => TRUE,
            ],
            [
              'text' => E::ts(''),
              'style' => 'default',
              'size' => 'btn-xs',
              'icon' => 'fa-bars',
              'links' => [
                [
                  'entity' => 'Activity',
                  'action' => 'view',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('View'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'condition' => [
                    'activity_type_id:name',
                    'NOT IN',
                    ['Contribution'],
                  ],
                ],
                [
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('View Contrib.'),
                  'style' => 'default',
                  'path' => 'civicrm/contact/view/contribution?action=view&reset=1&id=[source_record_id]&cid=[source_contact_id]',
                  'task' => '',
                  'condition' => [
                    'activity_type_id:name',
                    '=',
                    'Contribution',
                  ],
                ],
                [
                  'entity' => 'Activity',
                  'action' => 'update',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-pencil',
                  'text' => E::ts('Edit'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'condition' => [
                    'activity_type_id:name',
                    'NOT IN',
                    ['Contribution'],
                  ],
                ],
                [
                  'entity' => 'Activity',
                  'action' => 'delete',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-trash',
                  'text' => E::ts('Delete'),
                  'style' => 'danger',
                  'path' => '',
                  'task' => '',
                  'condition' => [
                    'activity_type_id:name',
                    'NOT IN',
                    ['Contribution'],
                  ],
                ],
              ],
              'type' => 'menu',
              'alignment' => 'text-right',
            ],
          ],
          'actions' => TRUE,
          'classes' => ['table', 'table-striped'],
          'headerCount' => TRUE,
          'actions_display_mode' => 'menu',
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];
