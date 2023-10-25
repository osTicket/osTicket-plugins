<?php
$args = array();
parse_str($_SERVER['QUERY_STRING'], $args);
unset($args['p'], $args['_pjax']);

if ($staffId = $staff->getId()) {
    $events = AuditEntry::getTableInfo($staff);
    $total = count($events);
    $qwhere = AuditEntry::getQwhere($staff);
    $pageNav=AuditEntry::getPageNav($qwhere);
    $pageNav->setURL('staff.php', $args);
}

 ?>
<h3><?php echo __('Agent Audit History'); ?></h3>
<hr style="opacity:0.3"/>

<div><?php echo __(
"This table shows the history of everything <b>" . $staff->getName() . "</b> has performed. <br>"
); ?>
</div>

<div class="pull-left" style="margin-top:5px;">
   <?php
    if ($total)
        echo '<strong>'.$pageNav->showing().'</strong>';
    else
        echo sprintf(__('%s does not have any audits'), __('Agent'));
   ?>
</div>

<?php if ($total) { ?>
<table class="list" id="agent-audit" style="width:100%">
    <thead>
    <tr>
        <th>Description</th>
        <th>Timestamp</th>
        <th>IP Address</th>
    </tr>
    </thead>
    <tbody>

        <?php
        foreach ($events as $data) { ?>
          <tr data-staff-id="<?php echo $staffId; ?>">
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
    if ($staffId) echo '&nbsp;'.__('Page').':'.$pageNav->getPageLinks('audits').'&nbsp;';
    echo sprintf('<a href="ajax.php/audit/export/sid,%d" id="%s" class="no-pjax nomodalexport">%s</a>',
        $staffId,
        'audit-export',
        __('Export'));
    echo '</div>';
}
?>
