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
}

    function enable() {
        AuditEntry::autoCreateTable();
        return parent::enable();
    }
}
