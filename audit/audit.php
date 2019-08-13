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

        Signal::connect('ajax.scp', function($dispatcher) {
            $dispatcher->append(
                url('^/audit/export/build/(?P<type>\w+)/(?P<state>\w+)|uid,(?P<uid>\d+)|sid,(?P<sid>\d+)|tid,(?P<tid>\d+)$',
                function($type=NULL, $state=NULL, $uid=NULL, $sid=NULL, $tid=NULL) {
                    global $thisstaff;

                    if (!$thisstaff)
                        Http::response(403, 'Agent login is required');

                    $show = AuditEntry::$show_view_audits;
                    if ($type) {
                        foreach (AuditEntry::getTypes() as $abbrev => $info) {
                            if ($type == $abbrev)
                               $name = AuditEntry::getObjectName($info[0]);
                        }
                        $filename = sprintf('%s-audits-%s.csv', $name, strftime('%Y%m%d'));
                        Export::audits('audit', $type, $state, $filename, '', '', 'csv', $show);
                    } elseif ($uid) {
                        $userName = User::getNameById($uid);
                        $filename = sprintf('%s-audits-%s.csv', $userName->name, strftime('%Y%m%d'));
                        Export::audits('user', '', '', $filename, $tableInfo, $uid, 'csv', $show);
                    } elseif ($sid) {
                        $staff = Staff::lookup($sid);
                        $filename = sprintf('%s-audits-%s.csv', $staff->getName(), strftime('%Y%m%d'));
                        Export::audits('staff', '', '', $filename, $tableInfo, $sid, 'csv', $show);
                    } elseif ($tid) {
                        $ticket = Ticket::lookup($tid);
                        $filename = sprintf('%s-audits-%s.csv', $ticket->getNumber(), strftime('%Y%m%d'));
                        Export::audits('ticket', '', '', $filename, $tableInfo, $tid, 'csv', $show);
                    }
                })
            );
        });

        Signal::connect('ajax.scp', function($dispatcher) {
            $dispatcher->append(
                url('^/audit/export/status$', function() {
                    if(!($maxtime = ini_get('max_execution_time')))
                        $maxtime = 30;

                    if ($_SESSION['export']['end']) {
                        if (intval($_SESSION['export']['end'] - $_SESSION['export']['start']) >= $maxtime) {
                            $response = array('status' => 'email');
                        } else {
                            $response = array(
                                'status' => 'download',
                                'filename' => $_SESSION['export']['filename'],
                            );
                        }
                    } else
                        $response = array('status' => 'writing');
                    return JsonDataEncoder::encode($response);
                })
            );
        });

        Signal::connect('ajax.scp', function($dispatcher) {
            $dispatcher->append(
                url('^/audit/export/(?P<status>email|download)$', function($status) {
                    global $thisstaff;

                    if (!$status)
                        Http::response(403, 'Export status is required');

                    $filepath = $_SESSION['export']['tempath'];
                    $filename = $_SESSION['export']['filename'];
                    unset($_SESSION['export']);
                    if ($status === 'download') {
                        Http::download($filename, 'text/csv');
                        $file = readfile($filepath);
                        fclose($filepath);
                        exit();
                    } elseif ($status === 'email') {
                        Mailer::sendExportEmail($filename, $filepath, $thisstaff, 'audit');
                        fclose($filepath);
                    } else
                        Http::response(403, 'Unknown action');
                })
            );
        });
}

    function enable() {
        AuditEntry::autoCreateTable();
        return parent::enable();
    }
}
