<?php
require_once 'class.audit.php';
require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');

class AuditPlugin extends Plugin {
    var $config_class = 'AuditConfig';
    function bootstrap() {
        AuditEntry::bootstrap();
        $config = $this->getConfig();
        if ($config->get('show_view_audits'))
            AuditEntry::$show_view_audits = $config->get('show_view_audits');

        // Ticket audit
        Signal::connect('ticket.view.more', function($ticket, &$extras) {
            global $thisstaff;

            if (!$thisstaff || !$thisstaff->isAdmin())
                return;

            $extras[] = array(
                'url' => 'ajax.php/audit/ticket/' . $ticket->getId() . '/view',
                'icon' => 'icon-book',
                'name' => __('View Audit Log')
            );
        });

        // User audit
        Signal::connect('user.view.more', function($user, &$extras) {
            global $thisstaff;

            if (!$thisstaff || !$thisstaff->isAdmin())
                return;

            $extras[] = array(
                'url' => sprintf('phar:///%s/plugins/audit.phar/templates/user-audit.tmpl.php', INCLUDE_DIR),
                'icon' => 'icon-book',
                'name' => __('View Audit Log'),
                'tab' => __('audits')
            );
        });

        // Agent audit
        Signal::connect('agent.audit', function($staff, &$extras) {
            global $thisstaff;

            if (!$thisstaff || !$thisstaff->isAdmin())
                return;

            $extras[] = array(
                'url' => sprintf('phar:///%s/plugins/audit.phar/templates/agent-audit.tmpl.php', INCLUDE_DIR),
                'tab' => __('audits')
            );
        });

        // Ajax View Ticket Audit
        Signal::connect('ajax.scp', function($dispatcher) {
            $dispatcher->append(
                url_get('^/audit/ticket/(?P<id>\d+)/view$', function($ticketId) {
                    global $thisstaff;

                    $row = Ticket::objects()->filter(array('ticket_id'=>$ticketId))->values_flat('number')->first();
                    if (!$row)
                        Http::response(404, 'No such ticket');
                    if (!$thisstaff || !$thisstaff->isAdmin())
                        Http::response(403, 'Contact your administrator');

                    include 'templates/ticket-audit.tmpl.php';
                })
            );
        });

        // Ajax Audit Export
        Signal::connect('ajax.scp', function($dispatcher) {
             $dispatcher->append(
                 url('^/audit/export/(?P<type>\w+)/(?P<state>\w+)|uid,(?P<uid>\d+)|sid,(?P<sid>\d+)|tid,(?P<tid>\d+)$',
                 function($type=NULL, $state=NULL, $uid=NULL, $sid=NULL, $tid=NULL) {
                     global $thisstaff;

                     if (!$thisstaff)
                         Http::response(403, 'Agent login is required');

                     $show = AuditEntry::$show_view_audits;
                     $data = array();
                     if ($type) {
                         $url = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
                         $qarray = explode('&', $url);

                         foreach ($qarray as $key => $value) {
                             list($k, $v) = explode('=', $value);
                             $data[$k] = $v;
                         }
                         foreach (AuditEntry::getTypes() as $abbrev => $info) {
                             if ($type == $abbrev)
                                $name = AuditEntry::getObjectName($info[0]);
                         }
                         $filename = sprintf('%s-audits-%s.csv', $name, strftime('%Y%m%d'));
                         $export = array('audit', $filename, '', '', 'csv', $show, $data);
                     } elseif ($uid) {
                         $userName = User::getNameById($uid);
                         $filename = sprintf('%s-audits-%s.csv', $userName->name, strftime('%Y%m%d'));
                         $export = array('user', $filename, $tableInfo, $uid, 'csv', $show, $data);
                     } elseif ($sid) {
                         $staff = Staff::lookup($sid);
                         $filename = sprintf('%s-audits-%s.csv', $staff->getName(), strftime('%Y%m%d'));
                         $export = array('staff', $filename, $tableInfo, $sid, 'csv', $show, $data);
                     } elseif ($tid) {
                         $ticket = Ticket::lookup($tid);
                         $filename = sprintf('%s-audits-%s.csv', $ticket->getNumber(), strftime('%Y%m%d'));
                         $export = array('ticket', $filename, $tableInfo, $tid, 'csv', $show, $data);
                     }

                     try {
                         $interval = 5;
                         // Create desired exporter
                         $exporter = new CsvExporter();
                         $extra = array('filename' => $filename,
                                 'interval' => $interval);
                         // Register the export in the session
                         Exporter::register($exporter, $extra);
                         // Flush response / return export id and check interval
                         Http::flush(201, json_encode(['eid' =>
                                     $exporter->getId(), 'interval' => $interval]));
                         // Phew... now we're free to do the export
                         session_write_close(); // Release session for other requests
                         ignore_user_abort(1);  // Leave us alone bro!
                         @set_time_limit(0);    // Useless when safe_mode is on
                         // Export to the exporter
                         $export[] = $exporter;
                         call_user_func_array(array('Export', 'audits'), $export);
                         $exporter->close();
                         // Sleep 3 times the interval to allow time for file download
                         sleep($interval*3);
                         // Email the export if it exists
                         $exporter->email($thisstaff);
                         // Delete the file.
                         @$exporter->delete();
                         exit;
                     } catch (Exception $ex) {
                         $errors['err'] = __('Unable to prepare the export');
                     }

                     include 'templates/export.tmpl.php';
                 })
             );
         });
}

    function enable() {
        AuditEntry::autoCreateTable();
        return parent::enable();
    }
}
