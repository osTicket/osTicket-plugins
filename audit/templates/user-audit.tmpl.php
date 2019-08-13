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
        <th>Description</th>
        <th>Timestamp</th>
        <th>IP Address</th>
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
    echo sprintf('<a class="export-audit-csv no-pjax" href="#">%s</a>', __('Export'));
    echo '</div>';
}
?>
<script type="text/javascript">
$(function() {
    $('a.export-audit-csv').on('click', function(){
        showExportPopup("<?php echo __('User Audit Export'); ?>",
          '<i class="icon-spinner icon-spin icon-large"></i>&nbsp;&nbsp;'
          + "<?php echo __('Please wait while we generate the export.'); ?>"
        );
        $.ajax({
            type: "POST",
            url: 'ajax.php/audit/export/build/uid,<?php echo $user->getId(); ?>'
        });
        var popopts = {
            title: "<?php echo sprintf(__('%s Export'), $user->getName()); ?>",
            content: "<?php echo sprintf(
              __('The export has been sent to your email address at <b>%s</b>.'),
              $thisstaff->getEmail()); ?>",
        };
        checkExportStatus(
            'ajax.php/audit/export/status',
            'ajax.php/audit/export/',
            popopts
        );
    });
});
</script>
