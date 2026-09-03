<?php
require_once INCLUDE_DIR . 'class.signal.php';
require_once INCLUDE_DIR . 'class.plugin.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class.approval.php';

if (!defined('P_APPROVALS_DIR'))
    define('P_APPROVALS_DIR', __DIR__ . '/');

class TopicApprovalPlugin extends Plugin {

    var $config_class = 'TopicApprovalConfig';

    // One holding department / one approver matrix -> a single instance is enough.
    function isMultiInstance() {
        return false;
    }

    function bootstrap() {
        TopicApproval::setConfig($this->getConfig());
        TopicApproval::ensureSchema();
        // Editable notification templates (Admin Panel > Emails > Templates).
        TopicApproval::registerTemplates();
        TopicApproval::ensureTemplates();

        // 1) Hold freshly created tickets that belong to an approval topic.
        Signal::connect('ticket.created', array('TopicApproval', 'onTicketCreated'));

        // 2) Add "Approve / Reject" to the ticket action menu for approvers.
        Signal::connect('ticket.view.more', array($this, 'onTicketViewMore'));

        // 3) Register the decision endpoint on the staff ajax dispatcher.
        Signal::connect('ajax.scp', array($this, 'onAjaxScp'));

        // 4) While a ticket is pending: show the approval bar, force the
        //    Internal Note tab and hide reply / status / edit actions.
        Signal::connect('object.view', array($this, 'onObjectView'));
    }

    function onObjectView($object, $data = null) {
        global $thisstaff, $cfg;
        if (!$thisstaff || !$object instanceof Ticket)
            return;

        $rec = TopicApproval::getRecord($object->getId());
        if (!$rec || $rec['status'] !== TopicApproval::STATUS_PENDING)
            return;

        $ticket      = $object;
        $isApprover  = TopicApproval::isApprover($rec['topic_id'], $thisstaff);
        $requireReason = TopicApproval::rejectRequiresReason();
        $approvers   = TopicApproval::describeApprovers($rec['topic_id']);

        include P_APPROVALS_DIR . 'templates/ticket-guard.tmpl.php';
    }

    function onTicketViewMore($ticket, &$extras) {
        global $thisstaff;
        if (!$thisstaff || !$ticket instanceof Ticket)
            return;

        $rec = TopicApproval::getRecord($ticket->getId());
        if (!$rec || $rec['status'] !== TopicApproval::STATUS_PENDING)
            return;
        if (!TopicApproval::isApprover($rec['topic_id'], $thisstaff))
            return;

        $href = 'ajax.php/p-approvals/' . $ticket->getId() . '/decide';
        echo sprintf(
            '<li><a href="#%s" onclick="javascript:$.dialog($(this).attr(\'href\').substr(1)); return false;">'
            . '<i class="icon-check"></i> %s</a></li>',
            $href, __('Approve / Reject Ticket')
        );
    }

    function onAjaxScp($dispatcher) {
        $dispatcher->append(
            url('^/p-approvals/(?P<tid>\d+)/decide$', function ($tid) {
                global $thisstaff;

                if (!$thisstaff)
                    Http::response(403, 'Agent login required');

                $ticket = Ticket::lookup((int) $tid);
                if (!$ticket)
                    Http::response(404, 'No such ticket');

                $rec = TopicApproval::getRecord($ticket->getId());
                if (!$rec || $rec['status'] !== TopicApproval::STATUS_PENDING)
                    Http::response(404, 'Nothing to approve for this ticket');

                if (!TopicApproval::isApprover($rec['topic_id'], $thisstaff))
                    Http::response(403, 'You are not an approver for this topic');

                $errors = array();
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $decision = $_POST['decision'] ?? '';
                    $reason   = trim($_POST['reason'] ?? '');

                    if ($decision === 'approve') {
                        TopicApproval::approve($ticket, $thisstaff, $reason);
                        Http::response(201, json_encode(array(
                            'redirect' => 'tickets.php?id=' . $ticket->getId())));
                    } elseif ($decision === 'reject') {
                        if (TopicApproval::rejectRequiresReason() && !$reason) {
                            $errors['reason'] = __('A reason is required to reject.');
                        } else {
                            TopicApproval::reject($ticket, $thisstaff, $reason);
                            Http::response(201, json_encode(array(
                                'redirect' => 'tickets.php?id=' . $ticket->getId())));
                        }
                    } else {
                        $errors['err'] = __('Choose approve or reject.');
                    }
                }

                include P_APPROVALS_DIR . 'templates/decide.tmpl.php';
            })
        );
    }
}
?>
