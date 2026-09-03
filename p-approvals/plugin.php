<?php
return array(
    'id'          => 'ticket:topic-approval', # notrans
    'version'     => '1.0.0',
    'name'        => /* trans */ 'Ticket Topic Approval',
    'author'      => 'steelants',
    'description' => /* trans */ 'Tickets opened under a flagged Help Topic are held in a '
        . 'restricted department and stay invisible to every agent except the topic\'s '
        . 'designated approvers until one of them approves or rejects the ticket.',
    'url'         => 'https://github.com/steelants/osTicket-plugins',
    'plugin'      => 'p-approvals.php:TopicApprovalPlugin',
);
?>
