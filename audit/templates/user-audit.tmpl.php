<?php
$args = array();
parse_str($_SERVER['QUERY_STRING'], $args);
unset($args['p'], $args['_pjax']);
// Apply pagination
$events = AuditEntry::getTableInfo($user);
$total = count($events);
$qwhere = AuditEntry::getQwhere($user);
$pageNav=AuditEntry::getPageNav($qwhere);
$pageNav->setURL('users.php', $args);
 ?>
<h3><?php echo __('User Audit History'); ?></h3>
<hr style="opacity:0.3"/>

<div class="pull-left" style="margin-top:5px;">
   <?php
    if ($total)
        echo '<strong>'.$pageNav->showing().'</strong>';
    else
        echo sprintf(__('%s does not have any audits'), __('User'));
   ?>
</div>

<?php if ($total) { ?>
<table class="list" id="user-audit" style="width:100%">
    <thead>
    <tr>
        <th><?php echo __('Description'); ?></th>
        <th><?php echo __('Timestamp'); ?></th>
        <th><?php echo __('IP Address'); ?></th>
    </tr>
    </thead>
    <tbody>
      <?php
      foreach ($events as $data) { ?>
        <tr data-user-id="<?php echo $user->getId(); ?>">
            <td><?php echo $data['description']; ?></td>
            <td><?php echo $data['timestamp']; ?></td>
            <td><?php echo $data['ip']; ?></td>
        </tr>
      <?php
      }
      ?>
    </tbody>
</table>

<hr/>
<?php
    echo '<div>';
    echo '&nbsp;'.__('Page').':'.$pageNav->getPageLinks('audits').'&nbsp;';
    echo sprintf('<a href="ajax.php/audit/export/uid,%d" id="%s" class="no-pjax nomodalexport">%s</a>',
        $user->getId(),
        'audit-export',
        __('Export'));
    echo '</div>';
}
?>
