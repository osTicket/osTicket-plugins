<?php
/**
 * Domain logic + persistence for the Ticket Topic Approval plugin.
 *
 * A single row per pending/decided ticket is kept in
 *   <prefix>plugin_topic_approval
 * so we remember the department the ticket should land in once approved.
 */
require_once INCLUDE_DIR . 'class.thread.php';
require_once INCLUDE_DIR . 'class.template.php';

class TopicApproval {

    const TABLE = 'plugin_topic_approval';

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /** @var TopicApprovalConfig set by the plugin on bootstrap */
    static $config;

    static function setConfig($config) {
        self::$config = $config;
    }

    static function table() {
        return TABLE_PREFIX . self::TABLE;
    }

    /* ---------------------------------------------------------------- schema */

    static function ensureSchema() {
        static $done = false;
        if ($done)
            return;
        $done = true;

        // Thread-event names so approvals show up as timeline events, not just notes.
        db_query('INSERT IGNORE INTO ' . TABLE_PREFIX . "event (name) VALUES "
            . "('approvalheld'),('approvalgranted'),('approvalrejected')");

        $table = self::table();
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            `ticket_id` int(11) unsigned NOT NULL,
            `topic_id` int(11) unsigned NOT NULL DEFAULT 0,
            `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            `target_dept_id` int(11) unsigned NOT NULL DEFAULT 0,
            `requested_at` datetime DEFAULT NULL,
            `resolved_by` int(11) unsigned NOT NULL DEFAULT 0,
            `resolved_at` datetime DEFAULT NULL,
            `reason` text,
            PRIMARY KEY (`ticket_id`),
            KEY `status` (`status`)
        ) DEFAULT CHARSET=utf8";
        db_query($sql);
    }

    /* ---------------------------------------------------------------- config */

    /**
     * Normalise a stored config value into a flat list of selected keys.
     *
     * osTicket's ChoiceField (multiselect) stores its value as a JSON object
     * {"key":"label", ...}; single ChoiceField stores {"key":"label"} too.
     * Depending on when it is read it may already be decoded to an array.
     */
    static function selectedKeys($raw) {
        if ($raw === null || $raw === '' || $raw === array())
            return array();
        // Already a scalar key (e.g. single ChoiceField decoded to its key).
        if (is_int($raw) || is_bool($raw))
            return array((string) $raw);
        if (is_string($raw)) {
            $trim = trim($raw);
            if ($trim !== '' && $trim[0] !== '{' && $trim[0] !== '[')
                return array_values(array_filter(array_map('trim', explode(',', $trim))));
            $decoded = json_decode($trim, true);
            if (!is_array($decoded))
                return array();
            $raw = $decoded;
        }
        if (!is_array($raw))
            return array();
        // Associative {key:label} -> keys; list [key,...] -> values.
        $isAssoc = array_keys($raw) !== range(0, count($raw) - 1);
        return array_values(array_filter($isAssoc ? array_keys($raw) : $raw,
            function ($v) { return $v !== '' && $v !== null; }));
    }

    static function getHoldingDeptId() {
        if (!self::$config)
            return 0;
        $keys = self::selectedKeys(self::$config->get('holding-dept'));
        return $keys ? (int) reset($keys) : 0;
    }

    static function boolCfg($key, $default) {
        if (!self::$config)
            return $default;
        $v = self::$config->get($key);
        if ($v === null || $v === '')
            return $default;
        return !in_array(strtolower((string) $v), array('0', 'false', 'off', 'no'), true)
            && (bool) $v;
    }

    static function notifyApprovers()     { return self::boolCfg('notify-approvers', true); }
    static function notifyRequester()      { return self::boolCfg('notify-requester', true); }
    static function rejectRequiresReason() { return self::boolCfg('reject-requires-reason', true); }
    static function closeOnReject()        { return self::boolCfg('close-on-reject', true); }

    /* ------------------------------------------------------------ email templates */

    /**
     * Editable Email Templates registered under Admin Panel > Emails > Templates.
     * `subject`/`body` are seeded once into every template set by ensureTemplates().
     */
    static function templateDefs() {
        return array(
            'approval.needed' => array(
                'group'   => 'b.ticket.staff',
                'name'    => /* @trans */ 'Topic Approval - Needed (Agent)',
                'desc'    => /* @trans */ 'Alert sent to the topic approvers when a ticket is held for approval.',
                'context' => array('ticket', 'recipient'),
                'subject' => 'Approval needed: Ticket #%{ticket.number}',
                'body'    => '<p>Ticket <a href="%{ticket.staff_link}">#%{ticket.number}</a> '
                    . '&mdash; <strong>%{ticket.subject}</strong> &mdash; was opened under a Help Topic '
                    . 'that requires approval and is held in the <em>%{ticket.dept.name}</em> department '
                    . 'until an approver releases it.</p>',
            ),
            'approval.granted' => array(
                'group'   => 'a.ticket.user',
                'name'    => /* @trans */ 'Topic Approval - Approved (User)',
                'desc'    => /* @trans */ 'Notice sent to the requester when their ticket is approved.',
                'context' => array('ticket', 'recipient'),
                'subject' => 'Ticket #%{ticket.number} approved',
                'body'    => '<p>Hi %{recipient.name.first},</p>'
                    . '<p>Your ticket <strong>#%{ticket.number}</strong> &mdash; %{ticket.subject} &mdash; '
                    . 'has been approved and is now being processed.</p>',
            ),
            'approval.rejected' => array(
                'group'   => 'a.ticket.user',
                'name'    => /* @trans */ 'Topic Approval - Rejected (User)',
                'desc'    => /* @trans */ 'Notice sent to the requester when their ticket is rejected.',
                'context' => array('ticket', 'recipient'),
                'subject' => 'Ticket #%{ticket.number} rejected',
                'body'    => '<p>Hi %{recipient.name.first},</p>'
                    . '<p>Your ticket <strong>#%{ticket.number}</strong> &mdash; %{ticket.subject} &mdash; '
                    . 'has been rejected.</p>',
            ),
        );
    }

    /** Make the templates visible/editable in the admin UI. Call from bootstrap. */
    static function registerTemplates() {
        if (!class_exists('EmailTemplateGroup'))
            return;
        foreach (self::templateDefs() as $cn => $d) {
            EmailTemplateGroup::$all_names[$cn] = array(
                'group'   => $d['group'],
                'name'    => $d['name'],
                'desc'    => $d['desc'],
                'context' => $d['context'],
            );
        }
    }

    /** Seed the template bodies into each template set once. */
    static function ensureTemplates() {
        static $done = false;
        if ($done)
            return;
        $done = true;

        $tg  = TABLE_PREFIX . 'email_template_group';
        $tt  = TABLE_PREFIX . 'email_template';
        $defs = self::templateDefs();
        $names = "'" . implode("','", array_map('addslashes', array_keys($defs))) . "'";

        if (!($res = db_query('SELECT tpl_id FROM ' . $tg)))
            return;
        $groups = array();
        while ($r = db_fetch_array($res))
            $groups[] = (int) $r['tpl_id'];

        // Fast path: every set already has every template.
        $have = 0;
        if ($c = db_query('SELECT COUNT(*) AS n FROM ' . $tt . ' WHERE code_name IN (' . $names . ')'))
            $have = (int) (($row = db_fetch_array($c)) ? $row['n'] : 0);
        if ($have >= count($groups) * count($defs))
            return;

        foreach ($groups as $gid) {
            foreach ($defs as $cn => $d) {
                $c = db_query('SELECT id FROM ' . $tt
                    . ' WHERE tpl_id = ' . db_input($gid)
                    . ' AND code_name = ' . db_input($cn));
                if ($c && db_num_rows($c))
                    continue;
                db_query('INSERT INTO ' . $tt . ' SET created = NOW(), updated = NOW()'
                    . ', tpl_id = ' . db_input($gid)
                    . ', code_name = ' . db_input($cn)
                    . ', subject = ' . db_input($d['subject'])
                    . ', body = ' . db_input($d['body']));
            }
        }
    }

    /* ----------------------------------------------------------- notifications */

    static function mailer($ticket) {
        global $cfg;
        if (($d = $ticket->getDept()) && ($e = $d->getEmail()))
            return $e;
        return $cfg ? $cfg->getDefaultEmail() : null;
    }

    static function templateGroup($ticket) {
        global $cfg;
        if (($d = $ticket->getDept()) && ($g = $d->getTemplate()))
            return $g;
        return $cfg ? $cfg->getDefaultTemplate() : null;
    }

    /** Render one of our Email Templates for $ticket; returns ['subj'=>, 'body'=>] or null. */
    static function render($ticket, $codeName, $recipient, $extra = array()) {
        if (!($group = self::templateGroup($ticket)))
            return null;
        if (!($tpl = $group->getMsgTemplate($codeName)))
            return null;
        return $ticket->replaceVars($tpl->asArray(),
            array('recipient' => $recipient) + $extra);
    }

    /** Every approver agent + members of every approver team for a topic. */
    static function collectApproverStaff($topicId) {
        $staff = array();
        foreach (self::getApproverSpec($topicId) as $token) {
            $id = (int) substr($token, 1);
            if ($token[0] === 's' && ($s = Staff::lookup($id)))
                $staff[$s->getId()] = $s;
            elseif ($token[0] === 't' && ($t = Team::lookup($id))) {
                foreach ($t->getMembers() as $m)
                    $staff[$m->getId()] = $m;
            }
        }
        return $staff;
    }

    static function notifyApproversNeeded($ticket, $topicId) {
        if (!self::notifyApprovers() || !($email = self::mailer($ticket)))
            return;
        foreach (self::collectApproverStaff($topicId) as $s) {
            if (!$s->isAvailable())
                continue;
            if ($m = self::render($ticket, 'approval.needed', $s))
                $email->sendAlert($s, $m['subj'], $m['body']);
            else
                $email->sendAlert($s,
                    sprintf(__('Approval needed: Ticket #%s'), $ticket->getNumber()),
                    sprintf(__('Ticket #%s needs topic approval.'), $ticket->getNumber()));
        }
    }

    static function notifyRequesterDecision($ticket, $approved, $reason, Staff $by) {
        if (!self::notifyRequester() || !($email = self::mailer($ticket)))
            return;
        if (!($owner = $ticket->getOwner()) || !$owner->getEmail())
            return;
        $code = $approved ? 'approval.granted' : 'approval.rejected';
        if ($m = self::render($ticket, $code, $owner)) {
            $email->send($owner, $m['subj'], $m['body']);
            return;
        }
        $subject = $approved
            ? sprintf(__('Ticket #%s approved'), $ticket->getNumber())
            : sprintf(__('Ticket #%s rejected'), $ticket->getNumber());
        $body = $approved
            ? sprintf(__('Your ticket #%s has been approved.'), $ticket->getNumber())
            : sprintf(__('Your ticket #%s has been rejected.'), $ticket->getNumber());
        $email->send($owner, $subject, $body);
    }

    /**
     * Raw approver tokens (s<id> / t<id>) configured for a topic.
     */
    static function getApproverSpec($topicId) {
        if (!self::$config)
            return array();
        return self::selectedKeys(
            self::$config->get(TopicApprovalConfig::TOPIC_FIELD_PREFIX . (int) $topicId));
    }

    static function requiresApproval($topicId) {
        return (bool) self::getApproverSpec($topicId);
    }

    static function isApprover($topicId, $staff) {
        if (!$staff instanceof Staff)
            return false;
        foreach (self::getApproverSpec($topicId) as $token) {
            $kind = substr($token, 0, 1);
            $id   = (int) substr($token, 1);
            if ($kind === 's' && $id === (int) $staff->getId())
                return true;
            if ($kind === 't' && $staff->isTeamMember($id))
                return true;
        }
        return false;
    }

    /**
     * Human readable approver list for notes.
     */
    static function describeApprovers($topicId) {
        $names = array();
        foreach (self::getApproverSpec($topicId) as $token) {
            $id = (int) substr($token, 1);
            if ($token[0] === 's' && ($s = Staff::lookup($id)))
                $names[] = $s->getName();
            elseif ($token[0] === 't' && ($t = Team::lookup($id)))
                $names[] = sprintf('%s (%s)', $t->getName(), __('team'));
        }
        return implode(', ', $names);
    }

    /* -------------------------------------------------------------- records */

    static function getRecord($ticketId) {
        $sql = 'SELECT * FROM ' . self::table()
             . ' WHERE ticket_id = ' . db_input((int) $ticketId);
        $res = db_query($sql);
        return ($res && db_num_rows($res)) ? db_fetch_array($res) : null;
    }

    static function createPending($ticketId, $topicId, $targetDeptId) {
        $sql = 'INSERT INTO ' . self::table()
             . ' SET ticket_id = ' . db_input((int) $ticketId)
             . ', topic_id = ' . db_input((int) $topicId)
             . ', status = ' . db_input(self::STATUS_PENDING)
             . ', target_dept_id = ' . db_input((int) $targetDeptId)
             . ', requested_at = NOW()'
             . ' ON DUPLICATE KEY UPDATE ticket_id = ticket_id';
        return db_query($sql);
    }

    /* --------------------------------------------------------------- events */

    /**
     * Called from the ticket.created signal.
     */
    static function onTicketCreated($ticket) {
        if (!$ticket instanceof Ticket)
            return;

        $topicId = $ticket->getTopicId();
        if (!$topicId || !self::requiresApproval($topicId))
            return;

        // Already tracked (e.g. re-fired signal) -> nothing to do.
        if (self::getRecord($ticket->getId()))
            return;

        $holding = self::getHoldingDeptId();
        if (!$holding) {
            error_log('[p-approvals] No holding department configured; ticket '
                . $ticket->getId() . ' left in place.');
            return;
        }

        $targetDept = (int) $ticket->getDeptId();
        self::createPending($ticket->getId(), $topicId, $targetDept);

        if ($holding !== $targetDept)
            $ticket->setDeptId($holding);

        $approvers = self::describeApprovers($topicId);
        if ($approvers) {
            $msg = sprintf(
                __('This ticket was opened under a Help Topic that requires approval. '
                 . 'It is held in the "%1$s" department and is not visible to other '
                 . 'agents until it is approved. Approvers: %2$s.'),
                Dept::getNameById($holding) ?: (string) $holding,
                $approvers
            );
        } else {
            $msg = __('This ticket requires approval but no approvers are configured. '
                   . 'Please contact an administrator.');
        }
        $ticket->logNote(__('Awaiting topic approval'), $msg, 'SYSTEM', false);
        $ticket->logEvent('approvalheld', array('topic' => $topicId), 'SYSTEM');

        if ($approvers)
            self::notifyApproversNeeded($ticket, $topicId);
    }

    static function approve($ticket, Staff $staff, $reason = '') {
        $rec = self::getRecord($ticket->getId());
        if (!$rec || $rec['status'] !== self::STATUS_PENDING)
            return false;

        $target = (int) $rec['target_dept_id'];
        if ($target && $target !== (int) $ticket->getDeptId())
            $ticket->setDeptId($target);

        db_query('UPDATE ' . self::table()
            . ' SET status = ' . db_input(self::STATUS_APPROVED)
            . ', resolved_by = ' . db_input((int) $staff->getId())
            . ', resolved_at = NOW()'
            . ', reason = ' . db_input($reason)
            . ' WHERE ticket_id = ' . db_input((int) $ticket->getId()));

        $note = sprintf(__('Ticket approved by %s.'), (string) $staff->getName());
        if ($reason)
            $note .= "\n\n" . $reason;
        $ticket->logNote(__('Topic approval: approved'), $note, $staff, true);
        $ticket->logEvent('approvalgranted', array('reason' => $reason), $staff);
        self::notifyRequesterDecision($ticket, true, $reason, $staff);

        return true;
    }

    static function reject($ticket, Staff $staff, $reason = '') {
        $rec = self::getRecord($ticket->getId());
        if (!$rec || $rec['status'] !== self::STATUS_PENDING)
            return false;

        db_query('UPDATE ' . self::table()
            . ' SET status = ' . db_input(self::STATUS_REJECTED)
            . ', resolved_by = ' . db_input((int) $staff->getId())
            . ', resolved_at = NOW()'
            . ', reason = ' . db_input($reason)
            . ' WHERE ticket_id = ' . db_input((int) $ticket->getId()));

        $note = sprintf(__('Ticket rejected by %s.'), (string) $staff->getName());
        if ($reason)
            $note .= "\n\n" . $reason;
        $ticket->logNote(__('Topic approval: rejected'), $note, $staff, true);
        $ticket->logEvent('approvalrejected', array('reason' => $reason), $staff);
        self::notifyRequesterDecision($ticket, false, $reason, $staff);

        if (self::closeOnReject()) {
            $errors = array();
            if ($status = TicketStatus::lookup(array('state' => 'closed')))
                $ticket->setStatus($status, $reason ?: __('Rejected by approver'), $errors);
        }

        return true;
    }
}

/*
 * Thread timeline events. osTicket's ThreadEvent::getTypedEvent() auto-discovers
 * every ThreadEvent subclass by its static $state, so declaring these is enough
 * for the entries logged via Ticket::logEvent('approval*') to render with their
 * own wording and icon in the ticket history.
 */
class TopicApprovalHeldEvent extends ThreadEvent {
    static $icon = 'lock';
    static $state = 'approvalheld';
    function getIcon() { return static::$icon; }
    function getDescription($mode = self::MODE_STAFF) {
        return $this->template(__('Held for topic approval {timestamp}'), $mode);
    }
}

class TopicApprovalGrantedEvent extends ThreadEvent {
    static $icon = 'ok-circle';
    static $state = 'approvalgranted';
    function getIcon() { return static::$icon; }
    function getDescription($mode = self::MODE_STAFF) {
        return $this->template(
            __('Topic approval granted by <b>{somebody}</b> {timestamp}'), $mode);
    }
}

class TopicApprovalRejectedEvent extends ThreadEvent {
    static $icon = 'ban-circle';
    static $state = 'approvalrejected';
    function getIcon() { return static::$icon; }
    function getDescription($mode = self::MODE_STAFF) {
        return $this->template(
            __('Topic approval rejected by <b>{somebody}</b> {timestamp}'), $mode);
    }
}
?>
