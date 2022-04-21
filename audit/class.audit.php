<?php
define('AUDIT_TABLE', TABLE_PREFIX . 'audit');

class AuditEntry extends VerySimpleModel {
    static $meta = array(
        'table' => AUDIT_TABLE,
        'pk' => array('id'),
        'ordering' => array('-timestamp'),
        'select_related' => array('staff', 'user'),
        'joins' => array(
            'staff' => array(
                'constraint' => array('staff_id' => 'Staff.staff_id'),
                'null' => true,
            ),
            'user' => array(
                'constraint' => array('user_id' => 'User.id'),
                'null' => true,
            ),
        ),
    );

    //return an array with the object model, getName function, and url prefix
    static $types = array(
        'S' => array('Staff',               'getName',       'staff.php'),
        'B' => array('Canned',              'getTitle',      'canned.php'),
        'C' => array('Category',            'getName',       'categories.php'),
        'X' => array('ConfigItem',          'none',          'none'),
        'D' => array('Dept',                'getName',       'departments.php'),
        'M' => array('Email',               'getName',       'emails.php'),
        'I' => array('EmailTemplateGroup',  'getName',       'templates.php'),
        'Q' => array('FAQ',                 'getQuestion',   'faq.php'),
        'N' => array('DynamicForm',         'getTitle',      'forms.php'),
        'H' => array('Topic',               'getName',       'helptopics.php'),
        'L' => array('DynamicList',         'getName',       'lists.php'),
        'O' => array('Organization',        'getName',       'orgs.php'),
        'G' => array('Page',                'getName',       'pages.php'),
        'R' => array('Role',                'getName',       'roles.php'),
        'V' => array('SLA',                 'getName',       'slas.php'),
        'A' => array('Task',                'getNumber',     'tasks.php'),
        'E' => array('Team',                'getName',       'teams.php'),
        'T' => array('Ticket',              'getNumber',     'tickets.php'),
        'F' => array('Filter',              'getName',       'filters.php'),
        'U' => array('User',                'getName',       'users.php'),
        'J' => array('ClientAccount',       'getUserName',   'users.php'),
    );

    static function bootstrap() {
        Signal::connect('object.view', array('AuditEntry', 'auditObjectEvent'));
        Signal::connect('object.created', array('AuditEntry', 'auditObjectEvent'));
        Signal::connect('object.deleted', array('AuditEntry', 'auditObjectEvent'));
        Signal::connect('object.edited', array('AuditEntry', 'auditObjectEvent'));
        Signal::connect('person.login', array('AuditEntry', 'auditSpecialEvent'));
        Signal::connect('person.logout', array('AuditEntry', 'auditSpecialEvent'));
    }

    static function getObjectName($class) {
      switch ($class) {
        case 'Dept':
          return __('Department');
          break;
        case 'OrganizationModel':
          return __('Organization');
          break;
        case 'Canned':
          return __('Canned Response');
          break;
        case 'Topic':
          return __('Help Topic');
          break;
        case 'Staff':
          return __('Agent');
          break;
        case 'Filter':
          return __('Ticket Filter');
          break;
        case 'EmailTemplateGroup':
          return __('Email Template');
          break;
       case 'DynamicList':
          return __('List');
          break;
       case 'DynamicForm':
         return __('Form');
         break;
        case 'ConfigItem':
          return __('Configuration');
          break;
        case 'ClientAccount':
          return __('User Account');
          break;
        default:
          return $class;
          break;
      }
    }

    static $configurations = array(
        'time_format' => 'Time Format', //Configurations
        'date_format' => 'Date Format',
        'datetime_format' => 'Date and Time Format',
        'daydatetime_format' => 'Day Date and Time Format',
        'default_priority_id' => 'Default Priority',
        'reply_separator' => 'Reply Separator Tag',
        'isonline' => 'Helpdesk Status',
        'staff_ip_binding' => 'Bind Agent Session to IP',
        'staff_max_logins' => 'Staff Max Logins',
        'staff_login_timeout' => 'Staff Login TImeout',
        'staff_session_timeout' => 'Agent Session Timeout',
        'passwd_reset_period' => 'Password Expiration Policy',
        'client_max_logins' => 'User Max Logins',
        'client_login_timeout' => 'User Login TImeout',
        'client_session_timeout' => 'User Session Timeout',
        'max_page_size' => 'Default Page Size',
        'max_open_tickets' => 'Maximum Open Tickets',
        'autolock_minutes' => 'Collision Avoidance Duration',
        'default_priority_id' => 'Ticket Default Priority',
        'default_smtp_id' => 'Default MTA',
        'use_email_priority' => 'Emailed Tickets Priority',
        'enable_kb' => 'Enable Knowledge Base',
        'enable_premade' => 'Enable Canned Responses',
        'enable_captcha' => 'Human Verification:',
        'enable_auto_cron' => 'Fetch on auto-cron',
        'enable_mail_polling' => 'Email Fetching',
        'send_sys_errors' => 'System Errors',
        'send_sql_errors' => 'SQL Errors',
        'send_login_errors' => 'Excessive failed login attempts',
        'strip_quoted_reply' => 'Strip Quoted Reply',
        'ticket_autoresponder' => 'New Ticket Autoresponder',
        'message_autoresponder' => 'New Message Submitter Autoresponder',
        'ticket_notice_active' => 'New Ticket by Agent Autoresponder',
        'ticket_alert_active' => 'New Ticket Alert',
        'ticket_alert_admin' => 'Admin New Ticket Alert',
        'ticket_alert_dept_manager' => 'Manager New Ticket Alert',
        'ticket_alert_dept_members' => 'Dept Members New Ticket Alert',
        'message_alert_active' => 'New Message Alert',
        'message_alert_laststaff' => 'Last Respondent New Message Alert',
        'message_alert_assigned' => 'Assigned Agent / Team New Message Alert',
        'message_alert_dept_manager' => 'Department Manager New Message Alert',
        'note_alert_active' => 'New Internal Activity Alert',
        'note_alert_laststaff' => 'Last Respondent Internal Activity Alert',
        'note_alert_assigned' => 'Assigned Agent / Team Internal Activity Alert',
        'note_alert_dept_manager' => 'Department Manager Internal Activity Alert',
        'transfer_alert_active' => 'Ticket Transfer Alert',
        'transfer_alert_assigned' => 'Assigned Agent / Team Ticket Transfer Alert',
        'transfer_alert_dept_manager' => 'Department Manager Ticket Transfer Alert',
        'transfer_alert_dept_members' => 'Department Members Ticket Transfer Alert',
        'overdue_alert_active' => 'Overdue Ticket Alert',
        'overdue_alert_assigned' => 'Assigned Agent / Team Overdue Ticket Alert',
        'overdue_alert_dept_manager' => 'Department Manager Overdue Ticket Alert',
        'overdue_alert_dept_members' => 'Department Members Overdue Ticket Alert',
        'assigned_alert_active' => 'Ticket Assignment Alert',
        'assigned_alert_staff' => 'Assigned Agent Ticket Assignment Alert',
        'assigned_alert_team_lead' => 'Team Lead Ticket Assignment Alert',
        'assigned_alert_team_members' => 'Team Members Ticket Assignment Alert',
        'auto_claim_tickets' => 'Claim on Response',
        'collaborator_ticket_visibility' => 'Collaborator Tickets Visibility',
        'require_topic_to_close' => 'Require Help Topic to Close',
        'hide_staff_name' => 'Agent Identity Masking',
        'overlimit_notice_active' => 'Overlimit Notice Autoresponder',
        'email_attachments' => 'Email Attachments',
        'ticket_number_format' => 'Default Ticket Number Format',
        'ticket_sequence_id' => 'Default Ticket Number Sequence',
        'queue_bucket_counts' => 'Top-Level Ticket Counts',
        'task_number_format' => 'Default Task Number Format',
        'task_sequence_id' => 'Default Task Number Sequence',
        'log_level' => 'Default Log Level',
        'log_graceperiod' => 'Purge Logs',
        'client_registration' => 'Registration Method',
        'default_ticket_queue' => 'Default Ticket Queue',
        'accept_unregistered_email' => 'Accept All Emails',
        'add_email_collabs' => 'Accept Email Collaborators',
        'helpdesk_url' => 'Helpdesk URL',
        'helpdesk_title' => 'Helpdesk Name/Title',
        'default_dept_id' => 'Default Department',
        'enable_avatars' => 'Show Avatars',
        'enable_richtext' => 'Enable Rich Text',
        'default_locale' => 'Default Locale',
        'default_timezone' => 'Default Time Zone',
        'date_formats' => 'Date and Time Format',
        'system_language' => 'Primary Language',
        'add_secondary_language' => 'Secondary Languages',
        'default_storage_bk' => 'Store Attachments',
        'max_file_size' => 'Agent Maximum File Size',
        'files_req_auth' => 'Login required',
        'default_ticket_status_id' => 'Default Status',
        'default_sla_id' => 'Default SLA',
        'default_help_topic' => 'Default Help Topic',
        'ticket_lock' => 'Lock Semantics',
        'message_autoresponder_collabs' => 'New Message Participant Autoresponder',
        'ticket_alert_acct_manager' => 'Account Manager New Ticket Alert',
        'message_alert_acct_manager' => 'Account Manager New Message Alert',
        'default_task_priority_id' => 'Default Task Priority',
        'task_alert_active' => 'New Task Alert',
        'task_alert_admin' => 'New Task Admin Alert',
        'task_alert_dept_manager' => 'New Task Department Manager Alert',
        'task_alert_dept_members' => 'New Task Department Members Alert',
        'task_activity_alert_active' => 'New Task Activity Alert',
        'task_activity_alert_laststaff' => 'New Task Activity Last Respondent',
        'task_activity_alert_assigned' => 'New Task Activity Assigned Agent / Team',
        'task_activity_alert_dept_manager' => 'New Task Activity Department Manager',
        'task_assignment_alert_active' => 'Task Assignment Alert',
        'task_assignment_alert_staff' => 'Task Assignment Alert Assigned Agent / Team',
        'task_assignment_alert_team_lead' => 'Task Assignment Alert Team Lead',
        'task_assignment_alert_team_members' => 'Task Assignment Alert Team Members',
        'task_transfer_alert_active' => 'Task Transfer Alert',
        'task_transfer_alert_assigned' => 'Task Transfer Alert Assigned Agent / Team',
        'task_transfer_alert_dept_manager' => 'Task Transfer Alert Department Manager',
        'task_transfer_alert_dept_members' => 'Task Transfer Alert Department Members',
        'task_overdue_alert_active' => 'Overdue Task Alert',
        'task_overdue_alert_assigned' => 'Overdue Task Alert Assigned Agent / Team',
        'task_overdue_alert_dept_manager' => 'Overdue Task Alert Department Manager',
        'task_overdue_alert_dept_members' => 'Overdue Task Alert Department Members',
        'agent_name_format' => 'Agent Name Formatting',
        'agent_avatar' => 'Agent Avatar Source',
        'allow_pw_reset' => 'Allow Password Resets',
        'pw_reset_window' => 'Reset Token Expiration',
        'client_name_format' => 'User Name Formatting',
        'client_avatar' => 'User Avatar Source',
        'clients_only' => 'Registration Required',
        'allow_auth_tokens' => 'Authentication Token',
        'client_verify_email' => 'Client Quick Access',
        'restrict_kb' => 'Knowledgebase Require Client Login',
        'default_template_id' => 'Default Template Set',
        'default_email_id' => 'Default System Email',
        'alert_email_id' => 'Default Alert Email',
        'admin_email' => 'Admin Email Address',
        'verify_email_addrs' => 'Verify Email Addresses',
        'name' => 'Name', // Common Configurations
        'isactive' => 'Status',
        'notes' => 'Notes',
        'topic_id' => 'Help Topic', // Email Configurations
        'userid' => 'Username',
        'mail_active' => 'Mail Active',
        'mail_host' => 'Fetching Hostname',
        'mail_port' => 'Fetching Port',
        'mail_proto' => 'Mail Box Protocol',
        'mail_fetchfreq' => 'Fetch Frequency',
        'mail_fetchmax' => 'Emails Per Fetch',
        'postfetch' => 'Fetched Emails',
        'mail_archivefolder' => 'Mail Archive Folder',
        'smtp_active' => 'SMTP Active',
        'smtp_host' => 'SMTP Hostname',
        'smtp_port' => 'SMTP Port',
        'smtp_auth' => 'Authentication Required',
        'smtp_spoofing' => 'Header Spoofing',
        'mail_encryption' => 'Mail Encryption',
        'topic' => 'Name', // Help Topic Configurations
        'ispublic' => 'Type',
        'topic_pid' => 'Parent Topic',
        'dept_id' => 'Department',
        'custom-numbers' => 'Ticket Number Format',
        'number_format' => 'Number Format',
        'sequence_id' => 'Number Sequence',
        'priority_id' => 'Priority',
        'sla_id' => 'SLA Plan',
        'page_id' => 'Thank-You Page',
        'assign' => 'Auto-assign To',
        'noautoresp' => 'Auto-Response',
        'pid' => 'Parent', // Department Configurations
        'ispublic' => 'Type',
        'sla_id' => 'SLA',
        'manager_id' => 'Manager',
        'assignment_flag' => 'Ticket Assignment',
        'disable_auto_claim' => 'Claim on Response',
        'disable_reopen_auto_assign' => 'Reopen Auto Assignment',
        'email_id' => 'Outgoing Email',
        'tpl_id' => 'Template Set',
        'ticket_auto_response' => 'New Ticket',
        'message_auto_response' => 'New Message',
        'autoresp_email_id' => 'Auto-Response Email',
        'group_membership' => 'Recipients',
        'signature' => 'Department Signature',
        'grace_period' => 'Grace Period', // SLA Configurations
        'transient' => 'Transient',
        'disable_overdue_alerts' => 'Ticket Overdue Alerts',
        'type' => 'Type', // Page Configurations
        'body' => 'Page Content',
        'name_plural' => 'Plural Name', // List Configurations
        'sort_mode' => 'Sort Order',
        'ticket.activity.notice' => 'New Activity Notice', // Email Template Configurations
        'message.autoresp' => 'New Message Auto-response',
        'ticket.autoreply' => 'New Ticket Auto-reply',
        'ticket.autoresp' => 'New Ticket Auto-response',
        'ticket.notice' => 'New Ticket Notice',
        'ticket.overlimit' => 'Overlimit Notice',
        'note.alert' => 'Internal Activity Alert',
        'message.alert' => 'New Message Alert',
        'ticket.alert' => 'New Ticket Alert',
        'ticket.overdue' => 'Overdue Ticket Alert',
        'assigned.alert' => 'Ticket Assignment Alert',
        'transfer.alert' => 'Ticket Transfer Alert',
        'task.activity.alert' => 'New Activity Alert',
        'task.activity.notice' => 'New Activity Notice',
        'task.alert' => 'New Task Alert',
        'task.overdue.alert' => 'Overdue Task Alert',
        'task.assignment.alert' => 'Task Assignment Alert',
        'task.transfer.alert' => 'Task Transfer Alert',
        'firstname' => 'First Name', // Agent Configurations
        'lastname' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone Number',
        'phone_ext' => 'Phone Extension',
        'mobile' => 'Mobile Number',
        'username' => 'Username',
        'default_from_name' => 'Default From Name',
        'thread_view_order' => 'Thread View Order',
        'default_ticket_queue_id' => 'Default Ticket Queue',
        'reply_redirect' => 'Reply Redirect',
        'islocked' => 'Locked',
        'isadmin' => 'Administrator',
        'assigned_only' => 'Limit Access to Assigned',
        'onvacation' => 'Vacation Mode',
        'dept_access' => 'Department Access',
        'role_id' => 'Role',
        'passwd' => 'Password',
        'backend' => 'Backend',
        'lang' => 'Language',
        'timezone' => 'Timezone',
        'locale' => 'Locale',
        'isvisible' => 'Visible',
        'show_assigned_tickets' => 'Show Assigned Tickets',
        'change_passwd' => 'Change Password',
        'auto_refresh_rate' => 'Auto Refresh Rate',
        'default_signature_type' => 'Default Signature Type',
        'default_paper_size' => 'Default Paper Size',
        'user.create' => 'Create Users', // Agent Permissions
        'user.delete' => 'Delete Users',
        'user.edit' => 'Edit Users',
        'user.manage' => 'Manage Users',
        'user.dir' => 'User Directory',
        'org.create' => 'Create Organizations',
        'org.delete' => 'Delete Organizations',
        'org.edit' => 'Edit Organizations',
        'faq.manage' => 'Manage FAQs',
        'emails.banlist' => 'Banlist',
        'search.all' => 'Search All',
        'stats.agents' => 'Stats',
        'isenabled' => 'Status', // Team Configurations
        'lead_id' => 'Team Lead',
        'noalerts' => 'Assignment Alert',
        'ticket.assign' => 'Ticket Assign', // Role Configurations
        'ticket.close' => 'Ticket Close',
        'ticket.create' => 'Ticket Create',
        'ticket.delete' => 'Ticket Delete',
        'ticket.edit' => 'Ticket Edit',
        'thread.edit' => 'Ticket Thread Edit',
        'ticket.reply' => 'Ticket Reply',
        'ticket.refer' => 'Ticket Refer',
        'ticket.release' => 'Ticket Release',
        'ticket.transfer' => 'Ticket Transfer',
        'task.assign' => 'Task Assign',
        'task.close' => 'Task Close',
        'task.create' => 'Task Create',
        'task.delete' => 'Task Delete',
        'task.edit' => 'Task Edit',
        'task.reply' => 'Task Reply',
        'task.transfer' => 'Task Transfer',
        'canned.manage' => 'Manage Canned Responses',
        'execorder' => 'Execution Order', // Ticket Filter Configurations
        'target' => 'Target Channel',
        'match_all_rules' => 'Match All Criteria',
        'stop_onmatch' => 'Stop On Match',
        'manager' => 'Account Manager', // Organization Configurations
        'assign-am-flag' => 'Auto-Assignment',
        'contacts' => 'Contacts',
        'sharing' => 'Ticket Sharing',
        'collab-pc-flag' => 'Auto Collaboration - Primary Contacts',
        'collab-all-flag' => 'Auto Collaboration - Organization Members',
        'domain' => 'Email Domain',
        'org' => 'Organization', // User Account Configurations
        'timezone' => 'Timezone',
        'password' => 'Password',
        'locked-flag' => 'Administratively Locked',
        'unlocked-flag' => 'Unlocked',
        'pwreset-flag' => 'Password Reset Required',
        'pwreset-sent' => 'Send Password Reset EMail',
        'user-registered' => 'Registered',
        'user-org' => 'Add to Organization',
        'forbid-pwchange-flag' => 'User cannot change password',
    );

    static $show_view_audits;

    function __toString() {
        return (string) $this->id;
    }

    //allows you to specify which part of the $types array you want returned
    static function getTypeExtra($objectType, $infoType) {
      foreach (self::getTypes() as $key => $info) {
        if ($objectType == $key) {
          switch ($infoType) {
            case 'Model':
                $extra = __($info[0]);
                break;
            case 'Name':
                $extra = __($info[1]);
                break;
            case 'URL':
                $extra = __($info[2]);
                break;
          }
        }
      }
      return $extra;
    }

    static function getPageNav($qwhere) {
      $qselect = 'SELECT audit.* ';
      $qfrom=' FROM '.AUDIT_TABLE.' audit ';
      $total=db_count("SELECT count(*) $qfrom $qwhere");
      $page = ($_GET['p'] && is_numeric($_GET['p']))?$_GET['p']:1;
      //pagenate
      $pageNav=new Pagenate($total, $page, PAGE_LIMIT);

      return $pageNav;
    }

    static function getQwhere($objectId, $hide_views=false, $type='') {
      $class = is_object($objectId) ? get_class($objectId) : $objectId;
      switch ($class) {
        case 'User':
          $qwhere = sprintf(' WHERE audit.user_id=%s', is_object($objectId) ? $objectId->getId() : $objectId);
          break;
        case 'Staff':
          $qwhere = sprintf(' WHERE audit.staff_id=%s', is_object($objectId) ? $objectId->getId() : $objectId);
          break;
        case 'Ticket':
          $qwhere = sprintf(' WHERE audit.object_id=%s', is_object($objectId) ? $objectId->getId() : $objectId);
          break;
        case 'AuditEntry':
          $qwhere =' WHERE 1';
          $qwhere.=' AND object_type='.db_input($_REQUEST['type'] ?: 'D');
          if ($hide_views)
            $qwhere.=' AND event_id='.db_input(Event::getIdByName($_REQUEST['state']));
          if ($_REQUEST['state'] && $_REQUEST['state'] != __('All')) {
              $event_id = Event::getIdByName(lcfirst($_REQUEST['state']));
              $qwhere.=' AND event_id='.db_input($event_id);
          }

            //dates
            $startTime  =($_REQUEST['startDate'] && (strlen($_REQUEST['startDate'])>=8))?strtotime($_REQUEST['startDate']):0;
            $endTime    =($_REQUEST['endDate'] && (strlen($_REQUEST['endDate'])>=8))?strtotime($_REQUEST['endDate']):0;
            if( ($startTime && $startTime>time()) or ($startTime>$endTime && $endTime>0)){
                $startTime=$endTime=0;
            } else {
                if($startTime)
                    $qwhere.=' AND timestamp>=FROM_UNIXTIME('.$startTime.')';
                if($endTime)
                    $qwhere.=' AND timestamp<=FROM_UNIXTIME('.$endTime.')';
            }
          break;
          default:
          $qwhere = $type ? sprintf(' WHERE audit.object_id=%d AND audit.object_type = "%s"', $objectId, $type) :
                            sprintf('WHERE audit.object_id=%d', $objectId);
          break;

      }
      if (!self::$show_view_audits)
        $qwhere.=' AND event_id !='.db_input(Event::getIdByName('viewed'));

      return $qwhere;
    }

    static function getOrder($order) {
        $or = null;
        $orderWays=array('DESC'=>'DESC','ASC'=>'ASC');

        if ($order && $orderWays[strtoupper($order)])
            $or = $orderWays[strtoupper($order)];
        elseif($_REQUEST['order'] && $orderWays[strtoupper($_REQUEST['order'])])
            $or = $orderWays[strtoupper($_REQUEST['order'])];

        $or = $or ? $or : 'DESC';

        return $or;
    }

    static function getQuery($qs, $objectId, $pageNav, $export, $type='') {
      $qselect = 'SELECT audit.* ';
      $qfrom=' FROM '.AUDIT_TABLE.' audit ';
      $qwhere =self::getQwhere($objectId, false, $type);

      $sortOptions=array('id'=>'audit.id', 'object_id'=>'audit.object_id', 'state'=>'audit.state','type'=>'audit.object_type','ip'=>'audit.ip'
                          ,'timestamp'=>'audit.timestamp');
      $sort=($_REQUEST['sort'] && $sortOptions[strtolower($_REQUEST['sort'])])?strtolower($_REQUEST['sort']):'timestamp';
      //Sorting options...
      if($sort && $sortOptions[$sort]) {
          $order_column =$sortOptions[$sort];
      }
      $order_column=$order_column?$order_column:'timestamp';
      $order = self::getOrder($_REQUEST['order']);

      if($order_column && strpos($order_column,',')){
          $order_column=str_replace(','," $order,",$order_column);
      }
      $x=$sort.'_sort';
      $$x=' class="'.strtolower($order).'" ';
      $order_by="$order_column $order ";

      $query = sprintf("$qselect $qfrom $qwhere %s",
               $export ? '' : "ORDER BY $order_by LIMIT ".$pageNav->getStart().",".$pageNav->getLimit());

      return $query;
    }

    static function getTableInfo($objectId, $export=false, $type='') {
      $qs = array();
      if($_REQUEST['type']) {
          $qs += array('type' => $_REQUEST['type']);
      }

      //pagenate
      $qwhere =self::getQwhere($objectId, false, $type);
      $pageNav=self::getPageNav($qwhere);
      $query = self::getQuery($qs, $objectId, $pageNav, $export, $type);
      $audits=db_query($query);

      if($audits && ($num=db_num_rows($audits)))
          $showing=$pageNav->showing().' '.$title;

      $table = array();
      $count = 0;
      foreach ($audits as $event) {
        $class = is_object($objectId) ? get_class($objectId) : $objectId;

        $table[$count]['id'] = $event['id'];
        $table[$count]['staff_id'] = $event['staff_id'];
        $table[$count]['user_id'] = $event['user_id'];
        $table[$count]['event_id'] = $event['event_id'];
        $table[$count]['description'] = self::getDescription($event, $export);
        $table[$count]['timestamp'] = Format::datetime($event['timestamp']);
        $table[$count]['ip'] = $event['ip'];
        $count++;
      }
      return $table;
    }

    static function getTypes() {
      return self::$types;
    }

    static function getConfigurations() {
      return self::$configurations;
    }

    static function getDescription($event, $export=false, $userType='') {
      $event = is_object($event) ? $event->ht : $event;
      $data = json_decode($event['data'], true);
      $name = '';
      if (!is_array($event))
        $event = $event->ht;

      if (!$person = $data['person'])
        $person = $event['staff_id'] ? (Staff::lookup($event['staff_id'])) : (User::lookup($event['user_id']));

      if ($person)
        $name = is_string($person) ? $person : $person->getName()->name;

      if (!$userType)
        $userType = $event['staff_id'] ? __('Agent') : __('User');

      $model = AuditEntry::getTypeExtra($event['object_type'], 'Model');
      $objectName = AuditEntry::getObjectName($model);
      $link = $event['object_type'] ? AuditEntry::getObjectLink($event) : '';
      $eventName = Event::getNameById($event['event_id']);
      $description = sprintf(__('%s <strong>%s</strong> %s %s %s'),
                     $userType, $name, $eventName, $objectName, $link);

      switch ($eventName) {
        case 'message':
            $message = sprintf(__('%s <strong>%s</strong> posted a %s to %s %s'),
                           $userType, $name, $userType == 'Agent' ? 'reply' : 'message', $objectName, $link);
            break;
        case 'note':
            $message = sprintf(__('%s <strong>%s</strong> posted a %s to %s %s'),
                $userType, $name, $eventName, $objectName, $link);
            break;
        case 'collab':
          $msg = $data['add'] ? 'Added ' : 'Deleted ';
          $data = $data['add'] ?: $data['del'];
          $name = array();
          $i = 0;
          foreach ($data as $key => $value) {
            if (is_numeric($key) && $i < 5)
                $name[] = ($i < 4) ? $value['name'] : $value['name'] . '...';
            $i++;
          }
          $name = implode(',', $name);
          $message = sprintf(__('%s <strong>%s</strong> %s Collaborator(s): <strong>%s</strong> Ticket: %s'), $userType, $person, $msg, $name, $link);
          break;
        case 'edited':
            switch ($event['object_type']) {
                case 'X':
                    foreach (self::getConfigurations() as $key => $value) {
                        if ($data['key'] == $key)
                            $configuration = __($value);
                    }
                    $message = sprintf(__('<strong>%s</strong> %s %s: <strong>%s</strong>'), $name ?: $userType, $data['type'] ?: 'Edited', $objectName, $configuration ?: $data['key']);
                    break;
                case 'T':
                case 'A':
                    if ($data['fields']) {
                      $fields = array();
                      foreach ($data['fields'] as $key => $value) {
                        if (is_array($data['fields'][$key]) && $key == 'fields')
                            $key = key($data['fields'][$key]);
                        if (is_numeric($key)) {
                            $field = DynamicFormField::objects()->filter(array('id'=>$key))->values_flat('label')->first() ?: array();
                            $fields[] = $field[0];
                        } else {
                            $field[0] = ucfirst($key);
                        }
                        $message = sprintf(__('%s <strong>%s</strong> Edited Field(s): %s <strong>%s: %s</strong> '), $userType,
                                    $name ?: $userType,
                                    !empty($fields) ? implode(',',$fields) : ($field[0] ?: '-'),
                                    $objectName, $link);
                      }
                    }

                    break;
                default:
                    if ($data['key']) {
                        foreach (self::getConfigurations() as $key => $value) {
                            if ($data['key'] == $key)
                                $configuration = __($value);
                        }
                    }

                    $message = sprintf(__('<strong>%s</strong> %s %s %s %s'), $name ?: $userType, $data['status'] ?: 'Edited', $objectName, $link, $configuration ?: $data['key'] ?: '');
                    break;
            }
          break;
        case 'login':
        case 'logout':
            $message = sprintf(__('%s <strong>%s</strong> %s'),$userType, $name, $data['msg'] ?: Event::getNameById($event['event_id']));
          break;
        case 'referred':
        case 'transferred':
          foreach ($data as $key => $value) {
            $name = is_array($value) ? '' : $value;
            if ($key != 'name')
              $msg = sprintf(__('%s to %s <strong>%s</strong>'), $description, self::getObjectName(ucfirst($key)), $name);
          }
          $message = __($msg ?: $description);
          break;
        case 'assigned':
          foreach ($data as $key => $value) {
            $assignee = is_array($value) ? '' : $value;

            if ($key != 'name' && $value)
                $msg = sprintf(__('%s to %s <strong>%s</strong>'), $description, self::getObjectName(ucfirst($key)), $assignee);
            if ($key == 'claim')
              $msg = sprintf(__('Agent <strong>%s</strong> Claimed %s'),$name ?: 'Agent', $link);
            if ($key == 'auto')
              $msg = sprintf(__('Agent <strong>SYSTEM</strong> Auto Assigned %s to <strong>%s</strong>'),$link, $name ?: 'Agent');
          }
          $message = __($msg ?: Event::getNameById($event['event_id']));
          break;
        default:
          $message = __($description);
          break;
      }
      return $export ? strip_tags($message) : $message;
    }

    static function getDataById($id, $type) {
        $row = self::objects()
            ->filter(array('object_type'=>$type, 'object_id'=>$id))
            ->values_flat('object_type', 'object_id', 'data')
            ->first();

        return $row ? $row : 0;
    }

    static function getObjectLink($event) {
        $types = self::getTypes();
        $urlPrefix = self::getTypeExtra($event['object_type'], 'URL');
        $data = json_decode($event['data'], true);
        $urlIdPrefix = $event['object_type'] == 'I' ? 'tpl_id' : 'id';

        if ($event['event_id'] != 14)
            $link = sprintf('<a href="%s?%s=%d"><b>%s</b></a>', $urlPrefix, $urlIdPrefix, $event['object_id'], $data['name']);
        else
            $link = sprintf('<b>%s</b>', $data['name']);

      return $link;
    }

    static function auditEvent($event_id, $object, $info) {
        global $thisstaff, $thisclient;

        $event = static::create();

        if (isset($info['data']))
          $event->data = $info['data'];

        //set the object_type based on the object's class
        if (is_object($object)) {
            foreach (self::getTypes() as $key => $info2) {
              if (get_class($object) == $info2[0])
                $event->object_type = $key;
            }
            if ($event->object_type)
                $event->object_id = $object->ht['id'] ?: $object->getId();
            else
                return false;
        } else {
            $event->object_type = $object[0];
            $event->object_id = $object[1];
            $event->data = $object[2];
        }

        $event->event_id = $event_id;
        $event->ip = osTicket::get_client_ip();

        try {
            if ($thisstaff)
                $event->staff_id = $thisstaff->getId();
            elseif (is_object($object) && get_class($object) == 'Staff')
              $event->staff_id = $object->getId();
            elseif (is_object($object) && get_class($object) == 'User')
              $event->user_id = $object->getId();
            elseif ($info['uid'])
              $event->user_id = $info['uid'];
            elseif ($thisclient)
                $event->user_id = $thisclient->getId();

            return $event->save();
        } catch (Exception $e) {
            //TODO: Return an error message
        }

    }

    static function auditSpecialEvent($object, $info=array()) {
        $data = array('person' => $object ? $object->getName()->name : '',
                      'msg' => $info['msg'] ?: '');
        $info['data'] = json_encode($data);
        $event_id = Event::getIdByName($info['type']);
        return static::auditEvent($event_id, $object, $info);
    }

    static function auditObjectEvent($object, $info=array()) {
      global $thisstaff, $thisclient;

      $event_id = Event::getIdByName($info['type']);
      $types = self::getTypes();
      foreach ($types as $abbrev => $data) {
        if (is_object($object) && (get_class($object) == $data[0])) {
          switch ($abbrev) {
              case 'X':
                  $data = array('person' => $thisstaff ? $thisstaff->getName()->name : __('SYSTEM'), 'key' => $info['key']);
                  $info['data'] = json_encode($data);
                  break;
              default:
                  $keys = array('updated', 'flags', 'mail_lastfetch', 'permissions', 'status');
                  $classes = array('Email', 'Filter', 'Page', 'Role', 'Staff', 'Topic');
                  if ($info['orm_audit'] &&
                        (!in_array(get_class($object), $classes) || in_array($info['key'], $keys)))
                    return false;

                  if (is_null($thisstaff) && is_null($thisclient) &&
                      get_class($object) == 'Ticket' && $info['type'] != 'assigned') {
                      $person = $object->getUser()->getName()->name;
                  } elseif (is_null($thisstaff) && is_null($thisclient))
                    $person = __('SYSTEM');

                  $name = $object ? call_user_func(array($object, $data[1])) : __('NA');
                  $data = array('name' => is_object($name) ? $name->name : $name,
                                'person' => $person ? $person : ($thisstaff ? $thisstaff->getName()->name :
                                                       $thisclient->getName()->name));
                  foreach ($info as $key => $value) {
                      if ($key != 'type')
                          $data[$key] = $value;
                  }

                  $info['data'] = json_encode($data);
                  break;
          }
        }
      }
      if (!is_object($object)) {
        if (!is_array($object)) {
            if ($data = AuditEntry::getDataById($object, $info['abbrev']))
                $name = json_decode($data[2], true);
            else {
                $name = __('NA');
                $data = array($info['abbrev'], $object);
            }
            $info['data'] = json_encode($data);

            return static::auditEvent($event_id, $data, $info);
        } else
            $info['data'] = json_encode($object);
      }

      return static::auditEvent($event_id, $object, $info);
    }

    static function create($vars=array()) {
        $event = new static($vars);
        $event->timestamp = SqlFunction::NOW();
        return $event;
    }

    static function autoCreateTable() {
        global $ost;

        $sql = 'SHOW TABLES LIKE \''.TABLE_PREFIX.'audit\'';
        if (db_num_rows(db_query($sql)))
            return true;
        else {
            $event_type = array('login', 'logout', 'message', 'note');
            foreach($event_type as $eType) {
                $sql = sprintf("SELECT * FROM `%s` WHERE name = '%s'",
                      TABLE_PREFIX.'event', $eType);

               $res=db_query($sql);
               $count = db_num_rows($res);

               if($count > 0) {
                  $message = "Event '$eType' already exists.";
                  $ost->logWarning('Audit Log Installation: Add Events', $message, false);
              } else {
                  // Add event
                  $sql = sprintf("INSERT INTO `%s` (`id`, `name`, `description`)
                         VALUES
                         ('','%s',NULL)",
                          TABLE_PREFIX.'event', $eType);

                   if(!($res=db_query($sql))) {
                      $message = "Unable to add $eType event to `".TABLE_PREFIX.'event'."`.";
                      $ost->logWarning('Audit Log Installation: Add Events', $message, false);
                  }
              }
            }

            $sql = sprintf('CREATE TABLE `%s` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `object_type` char(1) NOT NULL DEFAULT \'\',
              `object_id` int(10) unsigned NOT NULL,
              `event_id` int(11) unsigned DEFAULT NULL,
              `staff_id` int(10) unsigned NOT NULL DEFAULT \'0\',
              `user_id` int(10) unsigned NOT NULL DEFAULT \'0\',
              `data` text,
              `ip` varchar(64) DEFAULT NULL,
              `timestamp` datetime NOT NULL,
              PRIMARY KEY (`id`),
              KEY `staff_id` (`staff_id`),
              KEY `object_type` (`object_type`,`object_id`)
          ) CHARSET=utf8', TABLE_PREFIX.'audit');
            return db_query($sql);
        }
    }
}
